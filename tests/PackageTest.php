<?php

use DreamFactory\Core\Components\Package\Package;

class PackageTest extends \DreamFactory\Core\Testing\TestCase
{
    const TEST_PACKAGE = __DIR__ . '/unit-test-pkg.zip';

    const TEST_PACKAGE_SECURED = __DIR__ . '/unit-test-pkg-secured.zip';

    const SECURED_PASSWORD = 'dream123!';

    protected function getSimpleTestPackageManifest()
    {
        return [
            'version' => Package::VERSION,
            'df_version' => config('app.version'),
            'secured' => false,
            'description' => 'A test package',
            'created_date' => date('Y-m-d H:i:s', time()),
            'service' => [
                'system' => [
                    'service' => ['files', 'email', 'user']
                ]
            ]
        ];
    }

    protected function getTestPackage($secured = false)
    {
        $testFile = static::TEST_PACKAGE;
        if($secured){
            $testFile = static::TEST_PACKAGE_SECURED;
        }
        if(file_exists($testFile)) {
            $file = __DIR__ . '/file-' . time() . '.zip';
            copy($testFile, $file);

            return $file;
        } else {
            throw new Exception('Test package file - ' . $this->testPackage . 'not found.');
        }
    }

    public function testGetManifestHeader()
    {
        $package = new Package();
        $header = $package->getManifestHeader();
        $headerKeys = array_keys($header);

        $this->assertTrue(
            $headerKeys == ['version', 'df_version', 'secured', 'description', 'created_date'],
            'Testing manifest header'
        );
        $this->assertEquals(Package::VERSION, array_get($header, 'version'), 'Testing manifest version');
        $this->assertEquals(config('app.version'), array_get($header, 'df_version'), 'Testing manifest df version');
        $this->assertFalse(array_get($header, 'secured'), 'Testing manifest secured header');
        $this->assertEmpty(array_get($header, 'description'), 'Testing manifest description');
        $this->assertLessThanOrEqual(
            time(),
            strtotime(array_get($header, 'created_date')),
            'Testing manifest created_date'
        );
    }

    public function testGetManifest()
    {
        $package = new Package();

        $this->assertTrue($package->getManifest() == [], 'Testing empty manifest');
        $this->assertTrue($package->getManifest('foo', 'bar') === 'bar', 'Testing default manifest value');
    }

    public function testIsValid()
    {
        $validPackage = new Package($this->getSimpleTestPackageManifest());
        $invalidPackage1 = new Package([
            'foobar' => 1,
            'service' => [
                'system' => [
                    'service' => ['files', 'email', 'user']
                ]
            ]
        ]);

        $this->assertTrue($this->invokeMethod($validPackage, 'isValid'), 'Testing isValid method');
        $this->setExpectedException(\DreamFactory\Core\Exceptions\InternalServerErrorException::class, 'Invalid package supplied.');
        $this->invokeMethod($invalidPackage1, 'isValid');
    }

    public function testSetPassword()
    {
        $package = new Package();
        $package->setPassword('secret123');

        $this->assertEquals('secret123', $package->getPassword(), 'Testing setPassword method');
    }

    public function testGetPassword()
    {
        $p = $this->getSimpleTestPackageManifest();
        $p['secured'] = true;
        $securedPackage = new Package($p, true, 'mypassword');

        $this->assertEquals('mypassword', $securedPackage->getPassword(), 'Testing getPassword method.');
    }

    public function testGetPasswordException1()
    {
        $p = $this->getSimpleTestPackageManifest();
        $p['secured'] = true;
        $securedPackage = new Package($p);

        $this->setExpectedException(
            \DreamFactory\Core\Exceptions\BadRequestException::class,
            'Password is required for secured package.'
        );
        $securedPackage->getPassword();
    }

    public function testGetPasswordException2()
    {
        $p = $this->getSimpleTestPackageManifest();
        $p['secured'] = true;
        $securedPackage = new Package($p, true, 'foo');

        $this->setExpectedException(
            \DreamFactory\Core\Exceptions\BadRequestException::class,
            'Password must be at least ' . Package::PASSWORD_LENGTH . ' characters long for secured package.'
        );
        $securedPackage->getPassword();
    }

    public function testIsUploadedFile()
    {
        $package = new Package();
        $uploadedFile = [
            'name' => 'foo',
            'tmp_name' => '/tmp/foobar',
            'type' => 'application/zip',
            'size' => 123
        ];

        $this->assertTrue(
            $this->invokeMethod($package, 'isUploadedFile', [$uploadedFile]),
            'Testing isUploadedFile for true (1)'
        );
        $uploadedFile['type'] = 'bad/type';
        $this->assertFalse(
            $this->invokeMethod($package, 'isUploadedFile', [$uploadedFile]),
            'Testing isUploadedFile for false (1)'
        );
        $uploadedFile['type'] = 'application/x-zip-compressed';
        $this->assertTrue(
            $this->invokeMethod($package, 'isUploadedFile', [$uploadedFile]),
            'Testing isUploadedFile for true (2)'
        );
        unset($uploadedFile['size']);
        $this->assertFalse(
            $this->invokeMethod($package, 'isUploadedFile', [$uploadedFile]),
            'Testing isUploadedFile for false (2)'
        );
    }

    public function testGetManifestFromZipFile()
    {
        $package = new Package($this->getTestPackage());
        $manifest = $this->invokeMethod($package, 'getManifestFromZipFile');

        $this->assertArrayHasKey('version', $manifest, 'Testing manifest header version read from zip file');
        $this->assertArrayHasKey('df_version', $manifest, 'Testing manifest header df_version read from zip file');
        $this->assertEquals(7, count(array_get($manifest, 'service.system.service')), 'Testing manifest system service count');

        $package2 = new Package($this->getTestPackage(true), true, static::SECURED_PASSWORD);
        $manifest = $this->invokeMethod($package2, 'getManifestFromZipFile');
        $this->assertEquals(7, count(array_get($manifest, 'service.system.service')), 'Testing manifest system service count');

        $testFile = $this->getTestPackage(true);
        $package3 = new Package($testFile, true, static::SECURED_PASSWORD);
        unlink($testFile);
        $this->setExpectedException(
            \DreamFactory\Core\Exceptions\InternalServerErrorException::class,
            'Failed to open imported zip file.'
        );
        $this->invokeMethod($package3, 'getManifestFromZipFile');
    }

    public function testGetManifestFromZipFileWithBadPassword()
    {
        $this->setExpectedException(\DreamFactory\Core\Exceptions\BadRequestException::class);
        new Package($this->getTestPackage(true));
    }

    public function testGetManifestFromLocalFile()
    {
        $testFile = $this->getTestPackage();
        $package = new Package($testFile);
        $manifest = $this->invokeMethod($package, 'getManifestFromLocalFile', [$testFile]);

        $this->assertArrayHasKey('version', $manifest, 'Testing manifest header version read from zip file');
        $this->assertArrayHasKey('df_version', $manifest, 'Testing manifest header df_version read from zip file');
        $this->assertEquals(7, count(array_get($manifest, 'service.system.service')), 'Testing manifest system service count');

        unlink($testFile);
        $this->setExpectedException(
            \DreamFactory\Core\Exceptions\InternalServerErrorException::class,
            'Failed to import. File not found ' . $testFile
        );
        $this->invokeMethod($package, 'getManifestFromLocalFile', [$testFile]);
    }

    public function testGetManifestFromLocalFileException()
    {
        $this->setExpectedException(
            \DreamFactory\Core\Exceptions\BadRequestException::class,
            'Only package files ending with \'zip\' are allowed for import'
        );
        new Package('foobar.pkg');
    }

    public function testGetManifestFromUploadedFile()
    {
        $file = $this->getTestPackage();
        $fakeUpload = [
            'name' => basename($file),
            'tmp_name' => $file,
            'type' => 'application/zip',
            'size' => filesize($file)
        ];
        $package = new Package($fakeUpload);
        $manifest = $package->getManifest();
        $this->assertEquals(7, count(array_get($manifest, 'service.system.service')), 'Testing manifest system service count');
    }

    public function testGetManifestFromUrlImport()
    {
        $testFile = $this->getTestPackage();
        $dstPath = base_path('public') . '/test-package.zip';
        copy($testFile, $dstPath);
        unlink($testFile);
        $url = $this->getBaseUrl() . '/test-package.zip';
        $package = new Package($url);
        $manifest = $package->getManifest();
        $this->assertEquals(7, count(array_get($manifest, 'service.system.service')), 'Testing manifest system service count');
        unlink($dstPath);
    }

    public function testGetManifestFromUrlImportInvalidUrl()
    {
        $url = 'http://example.com/blabla.zip';
        $this->setExpectedException(\DreamFactory\Core\Exceptions\InternalServerErrorException::class);
        new Package($url);
    }

    public function testGetManifestFromUrlImportInvalidFileType()
    {
        $url = 'http://example.com/blabla.pkg';
        $this->setExpectedException(\DreamFactory\Core\Exceptions\BadRequestException::class);
        new Package($url);
    }

    public function testGetStorageServices()
    {
        $testFile = $this->getTestPackage();
        $package = new Package($testFile);
        $services = $package->getStorageServices();
        $expected = '{"files":["pictures/"],"local":["pics/"]}';
        $this->assertEquals($expected, json_encode($services, JSON_UNESCAPED_SLASHES));
    }

    public function testGetNonStorageServices()
    {
        $testFile = $this->getTestPackage();
        $package = new Package($testFile);
        $services = $package->getNonStorageServices();
        $expected = '{"service":["files","logs","db","email","user","rws","local"],"role":["my-role","test-role"],"app":["api_docs","test-app"],"user":["areef.islam@gmail.com"],"admin":["areef.islam@yahoo.com"],"cors":[1],"email_template":["test-template"]}';

        $this->assertEquals($expected, json_encode(array_get($services, 'system'), JSON_UNESCAPED_SLASHES));
    }

    public function testGetExportStorageService()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        $package1 = new Package($manifest);
        $manifest['storage'] = 'files';
        $package2 = new Package($manifest);
        $manifest['storage'] = ['name' => 'blabla'];
        $package3 = new Package($manifest);
        $manifest['storage'] = ['id' => 3];
        $package4 = new Package($manifest);

        $this->assertEquals('foobar', $package1->getExportStorageService('foobar'));
        $this->assertEquals('files', $package2->getExportStorageService('foobar2'));
        $this->assertEquals('blabla', $package3->getExportStorageService('foobar3'));
        $this->assertEquals('files', $package4->getExportStorageService('foobar4'));
    }

    public function testGetExportStorageFolder()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        $package1 = new Package($manifest);
        $manifest['storage'] = ['folder' => '__EXPORTS'];
        $package2 = new Package($manifest);
        $manifest['storage'] = ['path' => 'EXPORT-PATH'];
        $package3 = new Package($manifest);

        $this->assertEquals('my-exports', $package1->getExportStorageFolder('my-exports'));
        $this->assertEquals('__EXPORTS', $package2->getExportStorageFolder('my-exports2'));
        $this->assertEquals('EXPORT-PATH', $package3->getExportStorageFolder('my-exports3'));
    }

    public function testGetExportFilename()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        $package1 = new Package($manifest);
        $manifest['storage'] = ['filename' => 'pkg1'];
        $package2 = new Package($manifest);
        $manifest['storage'] = ['file' => 'pkg2.pkg'];
        $package3 = new Package($manifest);

        $this->assertEquals(php_uname('n') . '_' . date('Y-m-d_H.i.s', time()) . '.zip', $package1->getExportFilename());
        $this->assertEquals('pkg1.zip', $package2->getExportFilename());
        $this->assertEquals('pkg2.pkg.zip', $package3->getExportFilename());
    }

    public function testSetManifestItemsException()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        unset($manifest['service']['system']);
        $this->setExpectedException(
            \DreamFactory\Core\Exceptions\InternalServerErrorException::class,
            'No items found in package manifest.'
        );
        new Package($manifest);
    }

    public function testIsFileService()
    {
        $package = new Package($this->getTestPackage());

        $this->assertFalse($package->isFileService('invalid-service-name'));
        $this->assertTrue($package->isFileService('local', 'pics/'));
    }

    public function testInitZipFile()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        $manifest['storage'] = ['filename' => 'pkg1'];
        $package = new Package($manifest);
        $package->initZipFile();
        $file = $this->getNonPublicProperty($package, 'zipFile');

        $this->assertEquals('pkg1.zip', basename($file));
        $zip = $this->getNonPublicProperty($package, 'zip');
        $zip->addFromString('foobar.txt', 'test txt file');
        $zip->close();
        $this->assertTrue(file_exists($file));
        unlink($file);
    }

    public function testZipping()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        $manifest['storage'] = ['filename' => 'pkg1'];
        $package = new Package($manifest);
        $package->initZipFile();
        $package->zipManifestFile($manifest);
        $package->zipResourceFile('foobar.txt', ['test'=>'resource']);
        $package->zipFile(static::TEST_PACKAGE, 'test.zip');
        $package->zipContent('test.txt', 'hello world');
        $package->saveZipFile('files', '__EXPORTS');

        $this->service = ServiceManager::getService('files');
        $result = $this->makeRequest(\DreamFactory\Core\Enums\Verbs::GET, '__EXPORTS/pkg1.zip');

        $this->assertEquals(200, $result->getStatusCode());
        $this->makeRequest(\DreamFactory\Core\Enums\Verbs::DELETE, '__EXPORTS/pkg1.zip');
    }

    public function testSecuredZipping()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        $manifest['storage'] = ['filename' => 'pkg1'];
        $manifest['secured'] = true;
        $package = new Package($manifest, true, static::SECURED_PASSWORD);
        $package->initZipFile();
        $package->zipManifestFile($manifest);
        $package->zipResourceFile('foobar.txt', ['test'=>'resource']);
        $package->zipFile(static::TEST_PACKAGE, 'test.zip');
        $package->zipContent('test.txt', 'hello world');
        $package->saveZipFile('files', '__EXPORTS');

        $this->service = ServiceManager::getService('files');
        $result = $this->makeRequest(\DreamFactory\Core\Enums\Verbs::GET, '__EXPORTS/pkg1.zip');

        $this->assertEquals(200, $result->getStatusCode());
        $this->makeRequest(\DreamFactory\Core\Enums\Verbs::DELETE, '__EXPORTS/pkg1.zip');
    }

    public function testZippingException()
    {
        $manifest = $this->getSimpleTestPackageManifest();
        $manifest['storage'] = ['filename' => 'pkg1'];
        $package = new Package($manifest);
        $package->initZipFile();
        $package->zipManifestFile($manifest);
        $package->zipResourceFile('foobar.txt', ['test'=>'resource']);
        $package->zipFile(static::TEST_PACKAGE, 'test.zip');
        $package->zipContent('test.txt', 'hello world');
        $this->setExpectedException(\DreamFactory\Core\Exceptions\InternalServerErrorException::class);
        $package->saveZipFile('bad-files-service', '__EXPORTS');
    }

}