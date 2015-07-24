<?php
namespace DreamFactory\Core\Testing;

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Enums\HttpStatusCodes;

abstract class FileServiceTestCase extends TestCase
{
    const FOLDER_1 = 'df-test-folder-1';
    const FOLDER_2 = 'df-test-folder-2';
    const FOLDER_3 = 'df-test-folder-3';
    const FOLDER_4 = 'df-test-folder-4';

    /************************************************
     * Testing POST
     ************************************************/

    public function testPOSTFolder()
    {
        $rs = $this->addFolder(array(
            "folder" => array(
                array("name" => static::FOLDER_1),
                array("name" => static::FOLDER_2)
            )
        ));

        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals(
            '{"folder":[{"name":"' .
            static::FOLDER_1 .
            '","path":"/' .
            static::FOLDER_1 .
            '"},{"name":"' .
            static::FOLDER_2 .
            '","path":"/' .
            static::FOLDER_2 .
            '"}],"file":[]}',
            $content
        );
    }

    public function testPOSTFolderWithCheckExist()
    {
        $payload = '{"folder":{"name":"' . static::FOLDER_2 . '"}}';

        $rs = $this->makeRequest(Verbs::POST, null, [], $payload);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);
        $this->assertEquals('{"folder":[{"name":"' .
            static::FOLDER_2 .
            '","path":"/' .
            static::FOLDER_2 .
            '"}],"file":[]}', $content);

        //$this->setExpectedException('\DreamFactory\Core\Exceptions\BadRequestException',
        //    "Folder '" . static::FOLDER_2 . "/' already exists.");
        $rs = $this->makeRequest(Verbs::POST, null, ['check_exist' => 'true'], $payload);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);
        $this->assertEquals('{"folder":[{"name":"' .
            static::FOLDER_2 .
            '","path":"/' .
            static::FOLDER_2 .
            '","error":{"message":"Folder \'' .
            static::FOLDER_2 .
            '/\' already exists."}}],"file":[]}', $content);
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

        $rs = $this->makeRequest(Verbs::POST, static::FOLDER_1 . '/', [], $payload);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $expected =
            '{"folder":[{"name":"f1","path":"/' .
            static::FOLDER_1 .
            '/f1"},{"name":"f2","path":"/' .
            static::FOLDER_1 .
            '/f2"}],"file":[{"name":"file1.txt","path":"/' .
            static::FOLDER_1 .
            '/file1.txt"},{"name":"file2.txt","path":"/' .
            static::FOLDER_1 .
            '/file2.txt"}]}';

        $this->assertEquals($expected, $content);
    }

    public function testPOSTZipFileFromUrl()
    {
        $rs = $this->makeRequest(Verbs::POST, static::FOLDER_1 . '/f1/', ['url' => 'http://df.local/testfiles.zip']);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals('{"file":[{"name":"testfiles.zip","path":"/' . static::FOLDER_1 . '/f1/testfiles.zip"}]}',
            $content);
    }

    public function testPOSTZipFileFromUrlWithExtractAndClean()
    {
        $rs = $this->makeRequest(
            Verbs::POST,
            static::FOLDER_1 . '/f2/',
            ['url' => 'http://df.local/testfiles.zip', 'extract' => 'true', 'clean' => 'true']
        );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertEquals('{"folder":{"name":"' . static::FOLDER_1 . '/f2","path":"/' . static::FOLDER_1 . '/f2/"}}',
            $content);
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGETFolderAndFile()
    {
        $rs = $this->makeRequest(Verbs::GET, static::FOLDER_1 . '/');
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains('"path":"/' . static::FOLDER_1 . '/f1/"', $content);
        $this->assertContains('"path":"/' . static::FOLDER_1 . '/f2/"', $content);
        $this->assertContains('"path":"/' . static::FOLDER_1 . '/file1.txt"', $content);
        $this->assertContains('"path":"/' . static::FOLDER_1 . '/file2.txt"', $content);
    }

    public function testGETFolders()
    {
        $rs = $this->makeRequest(Verbs::GET);
        $data = $rs->getContent();

        $names = array_column($data[static::$wrapper], 'name');
        $paths = array_column($data[static::$wrapper], 'path');

        $this->assertTrue((in_array(static::FOLDER_1, $names) && in_array(static::FOLDER_2, $names)));
        $this->assertTrue((in_array(static::FOLDER_1, $paths) && in_array(static::FOLDER_2, $paths)));
    }

    public function testGETFolderAsAccessComponents()
    {
        $rs = $this->makeRequest(Verbs::GET, null, ['as_access_components' => 'true']);

        $data = $rs->getContent();
        $resources = $data[static::$wrapper];

        $this->assertTrue(
            in_array("", $resources) &&
            in_array("*", $resources) &&
            in_array(static::FOLDER_1, $resources) &&
            in_array(static::FOLDER_2, $resources)
        );
    }

    public function testGETFolderIncludeProperties()
    {
        $rs = $this->makeRequest(Verbs::GET, null, ['include_properties' => 'true']);
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains('"name":', $content);
        $this->assertContains('"path":', $content);
        $this->assertContains('"last_modified":', $content);
    }

    /************************************************
     * Testing DELETE
     ************************************************/

    public function testDELETEfile()
    {
        $rs1 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/file1.txt");
        $rs2 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/file2.txt");

        $this->assertEquals('{"file":[{"path":"/' . static::FOLDER_1 . '/file1.txt"}]}',
            json_encode($rs1->getContent(), JSON_UNESCAPED_SLASHES));
        $this->assertEquals('{"file":[{"path":"/' . static::FOLDER_1 . '/file2.txt"}]}',
            json_encode($rs2->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEZipFile()
    {
        $rs = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f1/testfiles.zip");
        $this->assertEquals('{"file":[{"path":"/' . static::FOLDER_1 . '/f1/testfiles.zip"}]}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEFolderByForce()
    {
        $rs = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f2/testfiles/", ['force' => 'true']);
        $this->assertEquals('{"folder":[{"path":"/' . static::FOLDER_1 . '/f2/testfiles/"}]}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEfolder()
    {
        $rs1 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f1/");
        $rs2 = $this->makeRequest(Verbs::DELETE, static::FOLDER_1 . "/f2/");

        $this->assertEquals('{"folder":[{"path":"/' . static::FOLDER_1 . '/f1/"}]}',
            json_encode($rs1->getContent(), JSON_UNESCAPED_SLASHES));
        $this->assertEquals('{"folder":[{"path":"/' . static::FOLDER_1 . '/f2/"}]}',
            json_encode($rs2->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEFoldersWithPayload()
    {

        $payload = '{"folder":[{"name":"' . static::FOLDER_1 . '"},{"name":"' . static::FOLDER_2 . '"}]}';

        $rs = $this->makeRequest(Verbs::DELETE, null, [], $payload);

        $expected = '{"folder":[{"name":"' . static::FOLDER_1 . '"},{"name":"' . static::FOLDER_2 . '"}],"file":[]}';

        $this->assertEquals($expected, json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDeleteFolderWithPathOnUrl()
    {
        $this->addFolder(array("folder" => array(array("name" => static::FOLDER_3))));

        $rs = $this->makeRequest(Verbs::DELETE, static::FOLDER_3 . "/");

        $this->assertEquals('{"folder":[{"path":"/' . static::FOLDER_3 . '/"}]}',
            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES));
    }

    public function testDELETEFolderWithPayload()
    {
        $this->addFolder(array("folder" => array(array("name" => static::FOLDER_4))));

        $payload = '{"folder":[{"name":"' . static::FOLDER_4 . '"}]}';

        $rs = $this->makeRequest(Verbs::DELETE, null, [], $payload);

        $this->assertEquals('{"folder":[{"name":"' . static::FOLDER_4 . '"}],"file":[]}',
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