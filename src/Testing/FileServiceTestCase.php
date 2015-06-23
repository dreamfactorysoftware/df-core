<?php
namespace DreamFactory\Core\Testing;

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Enums\HttpStatusCodes;

abstract class FileServiceTestCase extends TestCase
{
    const CONTAINER_1 = 'df-test-container-1';
    const CONTAINER_2 = 'df-test-container-2';
    const CONTAINER_3 = 'df-test-container-3';
    const CONTAINER_4 = 'df-test-container-4';

    /************************************************
     * Testing POST
     ************************************************/

    public function testPOSTContainer()
    {
        $rs =
            $this->addContainer(array(
                "container" => array(
                    array("name" => static::CONTAINER_1),
                    array("name" => static::CONTAINER_2)
                )
            ));

        $content = json_encode($rs->getContent());

        $this->assertEquals(
            '{"container":[{"name":"' .
            static::CONTAINER_1 .
            '","path":"' .
            static::CONTAINER_1 .
            '"},{"name":"' .
            static::CONTAINER_2 .
            '","path":"' .
            static::CONTAINER_2 .
            '"}]}',
            $content
        );
    }

    public function testPOSTContainerWithCheckExist()
    {
        $payload = '{"name":"' . static::CONTAINER_2 . '"}';

        $rs = $this->makeRequest(Verbs::POST, null, [], $payload);
        $content = json_encode($rs->getContent());
        $this->assertEquals('{"name":"' . static::CONTAINER_2 . '","path":"' . static::CONTAINER_2 . '"}', $content);

        $this->setExpectedException('\DreamFactory\Core\Exceptions\BadRequestException',
            "Container '" . static::CONTAINER_2 . "' already exists.");
        $this->makeRequest(Verbs::POST, null, ['check_exist' => 'true'], $payload);
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

        $rs = $this->makeRequest(Verbs::POST, static::CONTAINER_1, [], $payload);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $expected =
            '{"folder":[{"name":"f1","path":"' .
            static::CONTAINER_1 .
            '/f1"},{"name":"f2","path":"' .
            static::CONTAINER_1 .
            '/f2"}],"file":[{"name":"file1.txt","path":"' .
            static::CONTAINER_1 .
            '/file1.txt"},{"name":"file2.txt","path":"' .
            static::CONTAINER_1 .
            '/file2.txt"}]}';

        $this->assertEquals($expected, $content);
    }

    public function testPOSTZipFileFromUrl()
    {
        $rs = $this->makeRequest(Verbs::POST, static::CONTAINER_1 . '/f1/', ['url' => 'http://df.local/testfiles.zip']);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals('{"file":[{"name":"testfiles.zip","path":"' . static::CONTAINER_1 . '/f1/testfiles.zip"}]}',
            $content);
    }

    public function testPOSTZipFileFromUrlWithExtractAndClean()
    {
        $rs = $this->makeRequest(
            Verbs::POST,
            static::CONTAINER_1 . '/f2/',
            ['url' => 'http://df.local/testfiles.zip', 'extract' => 'true', 'clean' => 'true']
        );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals('{"folder":{"name":"f2","path":"' . static::CONTAINER_1 . '/f2/"}}', $content);
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGETFolderAndFile()
    {
        $rs = $this->makeRequest(Verbs::GET, static::CONTAINER_1);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains('"path":"' . static::CONTAINER_1 . '/f1/"', $content);
        $this->assertContains('"path":"' . static::CONTAINER_1 . '/f2/"', $content);
        $this->assertContains('"path":"' . static::CONTAINER_1 . '/file1.txt"', $content);
        $this->assertContains('"path":"' . static::CONTAINER_1 . '/file2.txt"', $content);
    }

    public function testGETContainers()
    {
        $rs = $this->makeRequest(Verbs::GET);
        $data = $rs->getContent();

        $names = array_column($data['resource'], 'name');
        $paths = array_column($data['resource'], 'path');

        $this->assertTrue((in_array(static::CONTAINER_1, $names) && in_array(static::CONTAINER_2, $names)));
        $this->assertTrue((in_array(static::CONTAINER_1, $paths) && in_array(static::CONTAINER_2, $paths)));
    }

    public function testGETContainerAsAccessComponents()
    {
        $rs = $this->makeRequest(Verbs::GET, null, ['as_access_components' => 'true']);

        $data = $rs->getContent();
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
        $rs = $this->makeRequest(Verbs::GET, null, ['include_properties' => 'true']);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains('"container":', $content);
        $this->assertContains('"last_modified":', $content);
    }

    /************************************************
     * Testing DELETE
     ************************************************/

    public function testDELETEfile()
    {
        $rs1 = $this->makeRequest(Verbs::DELETE, static::CONTAINER_1 . "/file1.txt");
        $rs2 = $this->makeRequest(Verbs::DELETE, static::CONTAINER_1 . "/file2.txt");

        $this->assertEquals('{"file":[{"path":"' . static::CONTAINER_1 . '/file1.txt"}]}',
            json_encode($rs1->getContent(), JSON_UNESCAPED_SLASHES));
        $this->assertEquals('{"file":[{"path":"' . static::CONTAINER_1 . '/file2.txt"}]}',
            json_encode($rs2->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEZipFile()
    {
        $rs = $this->makeRequest(Verbs::DELETE, static::CONTAINER_1 . "/f1/testfiles.zip");
        $this->assertEquals('{"file":[{"path":"' . static::CONTAINER_1 . '/f1/testfiles.zip"}]}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEFolderByForce()
    {
        $rs = $this->makeRequest(Verbs::DELETE, static::CONTAINER_1 . "/f2/testfiles/", ['force' => 'true']);
        $this->assertEquals('{"folder":[{"path":"' . static::CONTAINER_1 . '/f2/testfiles/"}]}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEfolder()
    {
        $rs1 = $this->makeRequest(Verbs::DELETE, static::CONTAINER_1 . "/f1/");
        $rs2 = $this->makeRequest(Verbs::DELETE, static::CONTAINER_1 . "/f2/");

        $this->assertEquals('{"folder":[{"path":"' . static::CONTAINER_1 . '/f1/"}]}',
            json_encode($rs1->getContent(), JSON_UNESCAPED_SLASHES));
        $this->assertEquals('{"folder":[{"path":"' . static::CONTAINER_1 . '/f2/"}]}',
            json_encode($rs2->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEContainerWithNames()
    {
        $rs = $this->makeRequest(Verbs::DELETE, null, ['names' => static::CONTAINER_1 . ',' . static::CONTAINER_2]);

        $expected = '{"container":[{"name":"' . static::CONTAINER_1 . '"},{"name":"' . static::CONTAINER_2 . '"}]}';

        $this->assertEquals($expected, json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEContainersWithPayload()
    {
        $this->addContainer(array(
            "container" => array(
                array("name" => static::CONTAINER_1),
                array("name" => static::CONTAINER_2)
            )
        ));

        $payload = '{"container":[{"name":"' . static::CONTAINER_1 . '"},{"name":"' . static::CONTAINER_2 . '"}]}';

        $rs = $this->makeRequest(Verbs::DELETE, null, [], $payload);

        $expected = '{"container":[{"name":"' . static::CONTAINER_1 . '"},{"name":"' . static::CONTAINER_2 . '"}]}';

        $this->assertEquals($expected, json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDeleteContainerWithPathOnUrl()
    {
        $this->addContainer(array("container" => array(array("name" => static::CONTAINER_3))));

        $rs = $this->makeRequest(Verbs::DELETE, static::CONTAINER_3 . "/");

        $this->assertEquals('{"name":"' . static::CONTAINER_3 . '"}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEContainerWithPayload()
    {
        $this->addContainer(array("name" => static::CONTAINER_4));

        $payload = '{"name":"' . static::CONTAINER_4 . '"}';

        $rs = $this->makeRequest(Verbs::DELETE, null, [], $payload);

        $this->assertEquals('{"name":"' . static::CONTAINER_4 . '","path":"' . static::CONTAINER_4 . '"}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    /************************************************
     * Helper methods.
     ************************************************/

    /**
     * @param array $containers
     *
     * @return \Illuminate\Http\Response
     */
    protected function addContainer(array $containers)
    {
        $payload = json_encode($containers);

        $rs = $this->makeRequest(Verbs::POST, null, [], $payload);

        return $rs;
    }
}