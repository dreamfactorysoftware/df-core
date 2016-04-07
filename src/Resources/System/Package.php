<?php
namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\Package\Exporter;
use DreamFactory\Core\Components\Package\Importer;
use DreamFactory\Core\Utility\FileUtilities;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Package extends BaseSystemResource
{
    /** @inheritdoc */
    protected function handleGET()
    {
        $systemOnly = $this->request->getParameterAsBool('system_only');
        $exporter = new Exporter(new \DreamFactory\Core\Components\Package\Package());
        $manifest = $exporter->getManifestOnly($systemOnly);

        if ($this->request->getParameterAsBool('as_file')) {
            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $fileName = $tmpDir . 'manifest_' . date('Y-m-d H:i:s', time()) . '.json';
            file_put_contents($fileName, json_encode($manifest, JSON_UNESCAPED_SLASHES));

            $rs = new StreamedResponse(function () use ($fileName){
                FileUtilities::sendFile($fileName, true);
            }, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment']);
            $rs->send();
            exit();
        } else {
            return $manifest;
        }
    }

    /** @inheritdoc */
    protected function handlePOST()
    {
        // Get uploaded file
        $file = $this->request->getFile('files');

        // Get file from a url
        if (empty($file)) {
            $file = $this->request->getPayloadData('import_url');
        }

        if (!empty($file)) {
            //Import
            $password = $this->request->input('password');
            $package = new \DreamFactory\Core\Components\Package\Package($file);
            $package->setPassword($password);
            $importer = new Importer($package, true);
            $importer->import();
            $log = $importer->getLog();

            return ['success' => true, 'log' => $log];
        } else {
            //Export
            $manifest = $this->request->getPayloadData();
            $package = new \DreamFactory\Core\Components\Package\Package($manifest);
            $exporter = new Exporter($package);
            $url = $exporter->export();
            $public = $exporter->isPublic();

            return ['success' => true, 'path' => $url, 'is_public' => $public];
        }
    }
}