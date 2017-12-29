<?php

namespace DreamFactory\Core\Testing;

use DreamFactory\Core\Enums\Verbs;

abstract class FileServiceTestCase extends TestCase
{
    const FOLDER_1   = 'df-test-folder-1';
    const FOLDER_2   = 'df-test-folder-2';
    const FOLDER_3   = 'df-test-folder-3';
    const FOLDER_4   = 'df-test-folder-4';

    /************************************************
     * Testing POST
     ************************************************/

    public function testPOSTFolder()
    {
        $rs = $this->addFolder([
            "resource" => [
                ["name" => static::FOLDER_1, "type" => "folder"]
            ]
        ]);

        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals(
            '{"resource":[{"name":"' .
            static::FOLDER_1 .
            '","path":"' .
            static::FOLDER_1 .
            '/","type":"folder"}]}',
            $content
        );
    }

    public function testPOSTFolderWithCheckExist()
    {
        $payload = '{"resource":[{"name":"' . static::FOLDER_2 . '", "type":"folder"}]}';

        $rs = $this->makeRequest(Verbs::POST, null, [], $payload);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);
        $this->assertEquals('{"resource":[{"name":"' .
            static::FOLDER_2 .
            '","path":"' .
            static::FOLDER_2 .
            '/","type":"folder"}]}', $content);

        //$this->setExpectedException('\DreamFactory\Core\Exceptions\BadRequestException',
        //    "Folder '" . static::FOLDER_2 . "/' already exists.");
        $rs = $this->makeRequest(Verbs::POST, null, ['check_exist' => 'true'], $payload);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);
        $this->assertEquals('{"resource":[{"name":"' .
            static::FOLDER_2 .
            '","path":"' .
            static::FOLDER_2 .
            '/","type":"folder","error":{"message":"Folder \'' .
            static::FOLDER_2 .
            '/\' already exists."}}]}', $content);
    }

    public function testPOSTFolderAndFile()
    {
        $payload =
            '{' .
            '"resource":[' .
            '{"name":"f1","type":"folder"},' .
            '{"name":"f2","type":"folder"},' .
            '{"name":"file1.txt","content":"Hello World 1","type":"file"},' .
            '{"name":"file2.txt","content":"Hello World 2","type":"file"}' .
            ']' .
            '}';

        $rs = $this->makeRequest(Verbs::POST, static::FOLDER_1 . '/', [], $payload);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $expected =
            '{"resource":[{"name":"f1","path":"' .
            static::FOLDER_1 .
            '/f1/","type":"folder"},{"name":"f2","path":"' .
            static::FOLDER_1 .
            '/f2/","type":"folder"},{"name":"file1.txt","path":"' .
            static::FOLDER_1 .
            '/file1.txt","type":"file"},{"name":"file2.txt","path":"' .
            static::FOLDER_1 .
            '/file2.txt","type":"file"}]}';

        $this->assertEquals($expected, $content);
    }

    public function testPOSTZipFileFromUrl()
    {
        $rs =
            $this->makeRequest(Verbs::POST, static::FOLDER_1 . '/f1/',
                ['url' => $this->getBaseUrl(). '/testfiles.zip']);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals('{"name":"testfiles.zip","path":"' .
            static::FOLDER_1 .
            '/f1/testfiles.zip","type":"file"}',
            $content);
    }

    public function testPOSTZipFileFromUrlWithExtractAndClean()
    {
        $rs = $this->makeRequest(
            Verbs::POST,
            static::FOLDER_1 . '/f2/',
            ['url' => $this->getBaseUrl() . '/testfiles.zip', 'extract' => 'true', 'clean' => 'true']
        );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals('{"name":"' .
            static::FOLDER_1 .
            '/f2","path":"' .
            static::FOLDER_1 .
            '/f2/","type":"file"}',
            $content);
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGETFolderAndFile()
    {
        $rs = $this->makeRequest(Verbs::GET, static::FOLDER_1 . '/');
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains('"path":"' . static::FOLDER_1 . '/f1/"', $content);
        $this->assertContains('"path":"' . static::FOLDER_1 . '/f2/"', $content);
        $this->assertContains('"path":"' . static::FOLDER_1 . '/file1.txt"', $content);
        $this->assertContains('"path":"' . static::FOLDER_1 . '/file2.txt"', $content);
    }

    public function testGETFolders()
    {
        $rs = $this->makeRequest(Verbs::GET);
        $data = $rs->getContent();

        $names = array_column($data[static::$wrapper], 'name');
        $paths = array_column($data[static::$wrapper], 'path');

        $this->assertTrue((in_array(static::FOLDER_1, $names) && in_array(static::FOLDER_2, $names)));
        $this->assertTrue((in_array(static::FOLDER_1 . '/', $paths) && in_array(static::FOLDER_2 . '/', $paths)));
    }

    // requires established session
//    public function testGETFolderAsAccessComponents()
//    {
//        $rs = $this->makeRequest(Verbs::GET, null, ['as_access_list' => 'true']);
//
//        $data = $rs->getContent();
//        $resources = $data[static::$wrapper];
//
//        $this->assertTrue(
//            in_array("", $resources) &&
//            in_array("*", $resources) &&
//            in_array(static::FOLDER_1 . '/', $resources) &&
//            in_array(static::FOLDER_2 . '/', $resources)
//        );
//    }

    public function testGETFolderIncludeProperties()
    {
        $rs = $this->makeRequest(Verbs::GET, null, ['include_properties' => 'true']);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains('"name":', $content);
        $this->assertContains('"path":', $content);
    }

    /************************************************
     * Testing DELETE
     ************************************************/

    public function testDELETEfile()
    {
        $rs1 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/file1.txt");
        $rs2 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/file2.txt");

        $this->assertEquals('{"name":"file1.txt","path":"' . static::FOLDER_1 . '/file1.txt"}',
            json_encode($rs1->getContent(), JSON_UNESCAPED_SLASHES));
        $this->assertEquals('{"name":"file2.txt","path":"' . static::FOLDER_1 . '/file2.txt"}',
            json_encode($rs2->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEZipFile()
    {
        $rs = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f1/testfiles.zip");
        $this->assertEquals('{"name":"testfiles.zip","path":"' . static::FOLDER_1 . '/f1/testfiles.zip"}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEFolderByForce()
    {
        $rs = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f2/testfiles/", ['force' => 'true']);
        $this->assertEquals('{"name":"testfiles","path":"' . static::FOLDER_1 . '/f2/testfiles/"}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEfolder()
    {
        $rs1 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f1/");
        $rs2 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f2/");

        $this->assertEquals('{"name":"f1","path":"' . static::FOLDER_1 . '/f1/"}',
            json_encode($rs1->getContent(), JSON_UNESCAPED_SLASHES));
        $this->assertEquals('{"name":"f2","path":"' . static::FOLDER_1 . '/f2/"}',
            json_encode($rs2->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEFoldersWithPayload()
    {

        $payload =
            '{"resource":[{"name":"' .
            static::FOLDER_1 .
            '", "path":"' .
            static::FOLDER_1 .
            '/", "type":"folder"},{"name":"' .
            static::FOLDER_2 .
            '", "path":"' .
            static::FOLDER_2 .
            '/", "type":"folder"}]}';

        $rs = $this->makeRequest(Verbs::DELETE, null, ['force' => true], $payload);

        $expected = $payload =
            '{"resource":[{"name":"' .
            static::FOLDER_1 .
            '","path":"' .
            static::FOLDER_1 .
            '/","type":"folder"},{"name":"' .
            static::FOLDER_2 .
            '","path":"' .
            static::FOLDER_2 .
            '/","type":"folder"}]}';

        $this->assertEquals($expected, json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDeleteFolderWithPathOnUrl()
    {
        $this->addFolder(["resource" => [["name" => static::FOLDER_3, "type" => "folder"]]]);

        $rs = $this->makeRequest(Verbs::DELETE, static::FOLDER_3 . "/");

        $this->assertEquals('{"name":"' . static::FOLDER_3 . '","path":"' . static::FOLDER_3 . '/"}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEFolderWithPayload()
    {
        $this->addFolder(["resource" => [["name" => static::FOLDER_4, "type" => "folder"]]]);

        $payload = '{"resource":[{"name":"' . static::FOLDER_4 . '", "path":"' . static::FOLDER_4 . '/", "type": "folder"}]}';

        $rs = $this->makeRequest(Verbs::DELETE, null, ['force' => true], $payload);

        $this->assertEquals('{"resource":[{"name":"' . static::FOLDER_4 . '","path":"' . static::FOLDER_4 . '/","type":"folder"}]}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    /************************************************
     * Helper methods.
     ************************************************/

    /**
     * @param array $folders
     *
     * @return \Illuminate\Http\Response
     */
    protected function addFolder(array $folders)
    {
        $payload = json_encode($folders);

        $rs = $this->makeRequest(Verbs::POST, null, [], $payload);

        return $rs;
    }
}