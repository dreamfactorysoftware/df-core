<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Rave\Testing;

use DreamFactory\Library\Utility\Enums\Verbs;

abstract class FileServiceTestCase extends TestCase
{
    const CONTAINER_1 = 'rave-test-container-1';
    const CONTAINER_2 = 'rave-test-container-2';
    const CONTAINER_3 = 'rave-test-container-3';
    const CONTAINER_4 = 'rave-test-container-4';

    protected $service = '';

    public function setUp()
    {
        parent::setUp();
        $this->setService();
    }

    protected abstract function setService();

    /************************************************
     * Testing POST
     ************************************************/

    public function testPOSTContainer()
    {
        $rs = $this->addContainer(array("container"=>array(array("name"=>static::CONTAINER_1), array("name"=>static::CONTAINER_2))));

        $this->assertEquals(
            '{"container":[{"name":"'.static::CONTAINER_1.'","path":"'.static::CONTAINER_1.'"},{"name":"'.static::CONTAINER_2.'","path":"'.static::CONTAINER_2.'"}]}',
            $rs->getContent()
        );
    }

    public function testPOSTContainerWithCheckExist()
    {
        $payload = '{"name":"'.static::CONTAINER_2.'"}';

        $rs = $this->callWithPayload(Verbs::POST, $this->prefix, $payload);
        $this->assertEquals('{"name":"'.static::CONTAINER_2.'","path":"'.static::CONTAINER_2.'"}', $rs->getContent());

        $rs = $this->callWithPayload(Verbs::POST, $this->prefix."?check_exist=true", $payload);
        $this->assertResponseStatus(400);
        $this->assertContains("Container '".static::CONTAINER_2."' already exists.", $rs->getContent());
    }

    public function testPOSTFolderAndFile()
    {
        $payload =
            '{' .
            '"folder":[' .
            '{"name":"f1"},' .
            '{"name":"f2"}' .
            '],' .
            '"file":[' .
            '{"name":"file1.txt","content":"Hello World 1"},' .
            '{"name":"file2.txt","content":"Hello World 2"}' .
            ']' .
            '}';

        $rs = $this->callWithPayload( Verbs::POST, $this->prefix . "/".static::CONTAINER_1."/", $payload );

        $expected = '{"folder":[{"name":"f1","path":"'.static::CONTAINER_1.'/f1"},{"name":"f2","path":"'.static::CONTAINER_1.'/f2"}],"file":[{"name":"file1.txt","path":"'.static::CONTAINER_1.'/file1.txt"},{"name":"file2.txt","path":"'.static::CONTAINER_1.'/file2.txt"}]}';

        $this->assertEquals($expected, $rs->getContent());
    }

    public function testPOSTZipFileFromUrl()
    {
        $rs = $this->call(Verbs::POST, $this->prefix."/".static::CONTAINER_1."/f1/?url=".urlencode('http://rave.local/testfiles.zip'));

        $this->assertEquals('{"file":[{"name":"testfiles.zip","path":"'.static::CONTAINER_1.'/f1/testfiles.zip"}]}', $rs->getContent());
    }

    public function testPOSTZipFileFromUrlWithExtractAndClean()
    {
        $rs = $this->call(Verbs::POST, $this->prefix."/".static::CONTAINER_1."/f2/?url=".urlencode('http://rave.local/testfiles.zip')."&extract=true&clean=true");

        $this->assertEquals('{"folder":{"name":"f2","path":"'.static::CONTAINER_1.'/f2/"}}', $rs->getContent());
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGETFolderAndFile()
    {
        $rs = $this->call(Verbs::GET, $this->prefix."/".static::CONTAINER_1."/");

        $this->assertContains('"path":"'.static::CONTAINER_1.'/f1/"', $rs->getContent());
        $this->assertContains('"path":"'.static::CONTAINER_1.'/f2/"', $rs->getContent());
        $this->assertContains('"path":"'.static::CONTAINER_1.'/file1.txt"', $rs->getContent());
        $this->assertContains('"path":"'.static::CONTAINER_1.'/file2.txt"', $rs->getContent());
    }

    public function testGETContainers()
    {
        $rs = $this->call(Verbs::GET, $this->prefix);

        $data = json_decode($rs->getContent(), true);
        $names = array_column($data['resource'], 'name');
        $paths = array_column($data['resource'], 'path');

        $this->assertTrue((in_array(static::CONTAINER_1, $names) && in_array(static::CONTAINER_2, $names)));
        $this->assertTrue((in_array(static::CONTAINER_1, $paths) && in_array(static::CONTAINER_2, $paths)));
    }

    public function testGETContainerAsAccessComponents()
    {
        $rs = $this->call(Verbs::GET, $this->prefix."?as_access_components=true");

        $data = json_decode($rs->getContent(), true);
        $resources = $data['resource'];

        $this->assertTrue(
            in_array("", $resources) &&
            in_array("*", $resources) &&
            in_array(static::CONTAINER_1, $resources) &&
            in_array(static::CONTAINER_2, $resources)
        );
    }

    public function testGETContainerIncludeProperties()
    {
        $rs = $this->call(Verbs::GET, $this->prefix."?include_properties=true");

        $this->assertContains('"container":', $rs->getContent());
        $this->assertContains('"last_modified":', $rs->getContent());
    }

    /************************************************
     * Testing DELETE
     ************************************************/

    public function testDELETEfile()
    {
        $rs1 = $this->call(Verbs::DELETE, $this->prefix."/".static::CONTAINER_1."/file1.txt");
        $rs2 = $this->call(Verbs::DELETE, $this->prefix."/".static::CONTAINER_1."/file2.txt");

        $this->assertEquals('{"file":[{"path":"'.static::CONTAINER_1.'/file1.txt"}]}', $rs1->getContent());
        $this->assertEquals('{"file":[{"path":"'.static::CONTAINER_1.'/file2.txt"}]}', $rs2->getContent());
    }

    public function testDELETEZipFile()
    {
        $rs = $this->call(Verbs::DELETE, $this->prefix."/".static::CONTAINER_1."/f1/testfiles.zip");
        $this->assertEquals('{"file":[{"path":"'.static::CONTAINER_1.'/f1/testfiles.zip"}]}', $rs->getContent());
    }

    public function testDELETEFolderByForce()
    {
        $rs = $this->call(Verbs::DELETE, $this->prefix."/".static::CONTAINER_1."/f2/testfiles/?force=1");
        $this->assertEquals('{"folder":[{"path":"'.static::CONTAINER_1.'/f2/testfiles/"}]}', $rs->getContent());
    }

    public function testDELETEfolder()
    {
        $rs1 = $this->call(Verbs::DELETE, $this->prefix."/".static::CONTAINER_1."/f1/");
        $rs2 = $this->call(Verbs::DELETE, $this->prefix."/".static::CONTAINER_1."/f2/");

        $this->assertEquals('{"folder":[{"path":"'.static::CONTAINER_1.'/f1/"}]}', $rs1->getContent());
        $this->assertEquals('{"folder":[{"path":"'.static::CONTAINER_1.'/f2/"}]}', $rs2->getContent());
    }

    public function testDELETEContainerWithNames()
    {
        $rs = $this->call(Verbs::DELETE, $this->prefix."?names=".static::CONTAINER_1.",".static::CONTAINER_2);

        $expected = '{"container":[{"name":"'.static::CONTAINER_1.'"},{"name":"'.static::CONTAINER_2.'"}]}';

        $this->assertEquals($expected, $rs->getContent());
    }

    public function testDELETEContainersWithPayload()
    {
        $this->addContainer(array("container"=>array(array("name"=>static::CONTAINER_1), array("name"=>static::CONTAINER_2))));

        $payload = '{"container":[{"name":"'.static::CONTAINER_1.'"},{"name":"'.static::CONTAINER_2.'"}]}';

        $rs = $this->callWithPayload(Verbs::DELETE, $this->prefix."", $payload);

        $expected = '{"container":[{"name":"'.static::CONTAINER_1.'"},{"name":"'.static::CONTAINER_2.'"}]}';

        $this->assertEquals($expected, $rs->getContent());
    }

    public function testDeleteContainerWithPathOnUrl()
    {
        $this->addContainer(array("container"=>array(array("name"=>static::CONTAINER_3))));

        $rs = $this->call(Verbs::DELETE, $this->prefix."/".static::CONTAINER_3."/");

        $this->assertEquals('{"name":"'.static::CONTAINER_3.'"}', $rs->getContent());
    }

    public function testDELETEContainerWithPayload()
    {
        $this->addContainer(array("name"=>static::CONTAINER_4));

        $payload = '{"name":"'.static::CONTAINER_4.'"}';

        $rs = $this->callWithPayload(Verbs::DELETE, $this->prefix, $payload);

        $this->assertEquals('{"name":"'.static::CONTAINER_4.'","path":"'.static::CONTAINER_4.'"}', $rs->getContent());
    }

    /************************************************
     * Helper methods.
     ************************************************/

    protected function addContainer(array $containers)
    {
        $payload = json_encode($containers);

        $rs = $this->callWithPayload(Verbs::POST, $this->prefix, $payload);

        return $rs;
    }
}