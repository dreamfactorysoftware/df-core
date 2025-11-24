<?php

namespace DreamFactory\Core\Components\ResourceImport;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\FileUtilities;

class Manager
{
    /** Supported file extensions */
    const FILE_EXTENSION = [CSV::FILE_EXTENSION];

    /** @var null|string File extension */
    protected $extension = null;

    /** @var  \DreamFactory\Core\Components\ResourceImport\Importable */
    protected $importer;

    /**
     * Manager constructor.
     *
     * @param string      $file
     * @param string      $service
     * @param null|string $resource
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public function __construct($file, $service, $resource = null)
    {
        if (is_array($file)) {
            $file = $this->verifyUploadedFile($file);
        } elseif (is_string($file)) {
            $file = $this->verifyImportFromUrl($file);
        } else {
            throw new BadRequestException('Invalid or no file supplied for import.');
        }
        $this->setImporter($file, $service, $resource);
    }

    /**
     * @return mixed
     */
    public function import()
    {
        return $this->importer->import();
    }

    /**
     * @param string      $file
     * @param string      $service
     * @param null|string $resource
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function setImporter($file, $service, $resource)
    {
        switch ($this->extension) {
            case CSV::FILE_EXTENSION:
                $this->importer = new CSV($file, $service, $resource);
                break;
            default:
                throw new BadRequestException('Importing file type [' . $this->extension . '] is not supported.');
        }
    }

    /**
     * @return string
     */
    public function getResource()
    {
        return $this->importer->getResource();
    }

    /**
     * Verifies the uploaed file for importing process.
     *
     * @param array $file
     *
     * @return string
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function verifyUploadedFile(array $file)
    {
        if (is_array($file['error'])) {
            throw new BadRequestException("Only a single file is allowed for import.");
        }

        if (UPLOAD_ERR_OK !== ($error = $file['error'])) {
            throw new InternalServerErrorException(
                "Failed to upload '" . $file['name'] . "': " . $error
            );
        }

        $this->checkFileExtension($file['name']);

        return $file['tmp_name'];
    }

    /**
     * Verifies file import from url.
     *
     * @param $url
     *
     * @return string
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function verifyImportFromUrl($url)
    {
        $this->checkFileExtension($url);
        try {
            // need to download and extract zip file and move contents to storage
            $file = FileUtilities::importUrlFileToTemp($url);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to import file from $url. {$ex->getMessage()}");
        }

        return $file;
    }

    /**
     * @param $filename
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function checkFileExtension($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, static::FILE_EXTENSION)) {
            throw new BadRequestException(
                "Unsupported file type. Supported types are " . implode(', ', static::FILE_EXTENSION)
            );
        }
        $this->extension = $extension;
    }
}