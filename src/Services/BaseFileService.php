<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

/**
 * Class BaseFileService
 *
 * @package DreamFactory\Core\Services
 */
abstract class BaseFileService extends BaseRestService
{
    /**
     * @var \DreamFactory\Core\Contracts\FileSystemInterface
     */
    protected $driver = null;
    /**
     * @var string Storage container name
     */
    protected $container = null;

    /**
     * @var string Full folder path of the resource
     */
    protected $folderPath = null;

    /**
     * @var string Full file path of the resource
     */
    protected $filePath = null;
    /**
     * @var array Array of private path strings
     */
    public $publicPaths = [];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::POST,
            Verbs::MERGE => Verbs::PATCH
        ];
        ArrayUtils::set($settings, "verbAliases", $verbAliases);
        parent::__construct($settings);
        $config = ArrayUtils::get($settings, 'config');
        $this->publicPaths = ArrayUtils::get($config, 'public_path', []);
        $this->setDriver($config);
    }

    /**
     * @return \DreamFactory\Core\Contracts\FileSystemInterface
     */
    public function driver()
    {
        return $this->driver;
    }

    /**
     * Sets the file system driver Local/S3/Azure/OStack...
     *
     * @param $config
     *
     * @return mixed
     */
    abstract protected function setDriver($config);

    /**
     * Apply the commonly used REST path members to the class.
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function setResourceMembers($resourcePath = null)
    {
        // File services need the trailing slash '/' for designating folders vs files
        // It is removed by the parent method
        $isFolder = (empty($resourcePath) ? false : ('/' === substr($resourcePath, -1)));
        parent::setResourceMembers($resourcePath);

        if (!empty($resourcePath)) {
            if ($isFolder) {
                $this->folderPath = $resourcePath;
            } else {
                $this->folderPath = dirname($resourcePath) . '/';
                $this->filePath = $resourcePath;
            }
        }

        return $this;
    }

    protected function getResourceIdentifier()
    {
        return 'path';
    }

    /**
     * @param array $resources
     *
     * @return bool|mixed
     * @throws BadRequestException
     * @throws NotFoundException
     */
    protected function handleResource(array $resources)
    {
        //  Fall through is to process just like a no-resource request
        $resources = $this->getResources(true);
        if ((false !== $resources) && !empty($this->resource)) {
            if (in_array($this->resource, $resources)) {
                return $this->processRequest();
            }
        }

        throw new NotFoundException("Resource '{$this->resource}' not found for service '{$this->name}'.");
    }

    protected function getAccessList()
    {
        $list = parent::getAccessList();

        $result = $this->driver->getFolder($this->container, '', false, true, true);
        foreach (array_column($result, 'path') as $resource) {
            $list[] = $resource;
            $list[] = $resource . '*';
        }

        return $list;
    }

    /**
     * Handles GET actions.
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     */
    protected function handleGET()
    {
        if (empty($this->folderPath) && empty($this->filePath) &&
            $this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST)
        ) {
            return ResourcesWrapper::wrapResources($this->getAccessList());
        }

        if (empty($this->filePath)) {
            //Resource is the root/container or a folder
            if ($this->request->getParameterAsBool('zip')) {
                $zipFileName = $this->driver->getFolderAsZip($this->container, $this->folderPath);
                FileUtilities::sendFile($zipFileName, true);
                unlink($zipFileName);

                // output handled by file handler, short the response here
                $this->setNativeFormat(null);
                $result = null;
            } elseif ($this->request->getParameterAsBool('include_properties')) {
                $result = $this->driver->getFolderProperties($this->container, $this->folderPath);
            } else {
                $result = $this->driver->getFolder(
                    $this->container,
                    $this->folderPath,
                    $this->request->getParameterAsBool('include_files', true),
                    $this->request->getParameterAsBool('include_folders', true),
                    $this->request->getParameterAsBool('full_tree', false)
                );

                $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
                $idField = $this->request->getParameter(ApiOptions::ID_FIELD, $this->getResourceIdentifier());
                $fields = $this->request->getParameter(ApiOptions::FIELDS, ApiOptions::FIELDS_ALL);

                $result = ResourcesWrapper::cleanResources($result, $asList, $idField, $fields, true);
            }
        } else {
            //Resource is a file
            if ($this->request->getParameterAsBool('include_properties', false)) {
                // just properties of the file itself
                $content = $this->request->getParameterAsBool('content', false);
                $result = $this->driver->getFileProperties($this->container, $this->filePath, $content);
            } else {
                $download = $this->request->getParameterAsBool('download', false);
                // stream the file, exits processing
                $this->streamFile($this->container, $this->filePath, $download);

                // output handled by file handler, short the response here
                $this->setNativeFormat(null);
                $result = null;
            }
        }

        return $result;
    }

    /**
     * Handles POST actions.
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws \Exception
     */
    protected function handlePOST()
    {
        if (empty($this->filePath)) {
            // create folders and files
            // possible file handling parameters
            $extract = $this->request->getParameterAsBool('extract', false);;
            $clean = $this->request->getParameterAsBool('clean', false);
            $checkExist = $this->request->getParameterAsBool('check_exist', false);

            $fileNameHeader = $this->request->getHeader('X-File-Name');
            $folderNameHeader = $this->request->getHeader('X-Folder-Name');
            $fileUrl = filter_var($this->request->getParameter('url', ''), FILTER_SANITIZE_URL);

            if (!empty($fileNameHeader)) {
                // html5 single posting for file create
                $result = $this->handleFileContent(
                    $this->folderPath,
                    $fileNameHeader,
                    $this->request->getContent(),
                    $this->request->getContentType(),
                    $extract,
                    $clean,
                    $checkExist
                );
            } elseif (!empty($folderNameHeader)) {
                // html5 single posting for folder create
                $fullPathName = $this->folderPath . $folderNameHeader;
                $content = $this->request->getPayloadData();
                $this->driver->createFolder($this->container, $fullPathName, $content);
                $result = ['name' => $folderNameHeader, 'path' => $fullPathName];
            } elseif (!empty($fileUrl)) {
                // upload a file from a url, could be expandable zip
                $tmpName = null;
                try {
                    $tmpName = FileUtilities::importUrlFileToTemp($fileUrl);
                    $result = $this->handleFile(
                        $this->folderPath,
                        '',
                        $tmpName,
                        '',
                        $extract,
                        $clean,
                        $checkExist
                    );
                    @unlink($tmpName);
                } catch (\Exception $ex) {
                    if (!empty($tmpName)) {
                        @unlink($tmpName);
                    }
                    throw $ex;
                }
            } elseif (null !== $uploadedFiles = $this->request->getFile('files')) {
                // older html multi-part/form-data post, single or multiple files
                $files = FileUtilities::rearrangePostedFiles($uploadedFiles);
                $result = $this->handleFolderContentFromFiles($files, $extract, $clean, $checkExist);
                $result = ResourcesWrapper::cleanResources($result);
            } else {
                // possibly xml or json post either of files or folders to create, copy or move
                if (!empty($data = ResourcesWrapper::unwrapResources($this->getPayloadData()))) {
                    $result = $this->handleFolderContentFromData($data, $extract, $clean, $checkExist);
                    $result = ResourcesWrapper::cleanResources($result);
                } else {
                    // create folder from resource path
                    $this->driver->createFolder($this->container, $this->folderPath);
                    $result = ['name' => basename($this->folderPath), 'path' => $this->folderPath];
                }
            }
        } else {
            // create the file
            // possible file handling parameters
            $extract = $this->request->getParameterAsBool('extract', false);
            $clean = $this->request->getParameterAsBool('clean', false);
            $checkExist = $this->request->getParameterAsBool('check_exist', false);
            $name = basename($this->filePath);
            $path = dirname($this->filePath);
            $files = $this->request->getFile('files');
            if (empty($files)) {
                // direct load from posted data as content
                // or possibly xml or json post of file properties create, copy or move
                $result = $this->handleFileContent(
                    $path,
                    $name,
                    $this->request->getContent(),
                    $this->request->getContentType(),
                    $extract,
                    $clean,
                    $checkExist
                );
            } else {
                // older html multipart/form-data post, should be single file
                $files = FileUtilities::rearrangePostedFiles($files);
                if (1 < count($files)) {
                    throw new BadRequestException("Multiple files uploaded to a single REST resource '$name'.");
                }
                $file = ArrayUtils::get($files, 0);
                if (empty($file)) {
                    throw new BadRequestException("No file uploaded to REST resource '$name'.");
                }
                $error = $file['error'];
                if (UPLOAD_ERR_OK == $error) {
                    $result = $this->handleFile(
                        $path,
                        $name,
                        $file["tmp_name"],
                        $file['type'],
                        $extract,
                        $clean,
                        $checkExist
                    );
                } else {
                    throw new InternalServerErrorException("Failed to upload file $name.\n$error");
                }
            }
        }

        return ResponseFactory::create($result, $this->nativeFormat, ServiceResponseInterface::HTTP_CREATED);
    }

    /**
     * Handles PATCH actions.
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     */
    protected function handlePATCH()
    {
        $content = $this->getPayloadData();
        if (empty($this->folderPath)) {
            // update container properties
            $this->driver->updateContainerProperties($this->container, $content);
        } else if (empty($this->filePath)) {
            // update folder properties
            $this->driver->updateFolderProperties($this->container, $this->folderPath, $content);
        } else {
            // update file properties?
            $this->driver->updateFileProperties($this->container, $this->filePath, $content);
        }

        return ['success' => true];
    }

    /**
     * Handles DELETE actions.
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     */
    protected function handleDELETE()
    {
        $force = $this->request->getParameterAsBool('force', false);

        if (empty($this->folderPath)) {
            // delete just folders and files from the container
            if (!empty($content = ResourcesWrapper::unwrapResources($this->request->getPayloadData()))) {
                $result = $this->deleteFolderContent($content, '', $force);
            } else {
                throw new BadRequestException('No resources given for delete.');
            }
        } else if (empty($this->filePath)) {
            // delete directory of files and the directory itself
            // multi-file or folder delete via post data
            if (!empty($content = ResourcesWrapper::unwrapResources($this->request->getPayloadData()))) {
                $result = $this->deleteFolderContent($content, $this->folderPath, $force);
            } else {
                $this->driver->deleteFolder($this->container, $this->folderPath, $force);
                $result = ['name' => basename($this->folderPath), 'path' => $this->folderPath];
            }
        } else {
            // delete file from permanent storage
            $this->driver->deleteFile($this->container, $this->filePath);
            $result = ['name' => basename($this->filePath), 'path' => $this->filePath];
        }

        return ResourcesWrapper::cleanResources($result);
    }

    /**
     * @param      $container
     * @param      $path
     * @param bool $download
     */
    public function streamFile($container, $path, $download = false)
    {
        $this->driver->streamFile($container, $path, $download);
    }

    /**
     * Checks to see if the path has a trailing slash. This is used for
     * determining whether a path is a folder or file.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function hasTrailingSlash($path)
    {
        return ('/' === substr($path, -1));
    }

    /**
     * @param        $dest_path
     * @param        $dest_name
     * @param        $content
     * @param string $contentType
     * @param bool   $extract
     * @param bool   $clean
     * @param bool   $check_exist
     *
     * @throws \Exception
     * @return array
     */
    protected function handleFileContent(
        $dest_path,
        $dest_name,
        $content,
        $contentType = '',
        $extract = false,
        $clean = false,
        $check_exist = false
    ){
        $ext = FileUtilities::getFileExtension($dest_name);
        if (empty($contentType)) {
            $contentType = FileUtilities::determineContentType($ext, $content);
        }
        if ((FileUtilities::isZipContent($contentType) || ('zip' === $ext)) && $extract) {
            // need to extract zip file and move contents to storage
            $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $tmpName = $tempDir . $dest_name;
            file_put_contents($tmpName, $content);
            $zip = new \ZipArchive();
            $code = $zip->open($tmpName);
            if (true !== $code) {
                unlink($tmpName);

                throw new InternalServerErrorException('Error opening temporary zip file. code = ' . $code);
            }

            $results = $this->extractZipFile($this->container, $dest_path, $zip, $clean);
            unlink($tmpName);

            return $results;
        } else {
            $fullPathName = FileUtilities::fixFolderPath($dest_path) . $dest_name;
            $this->driver->writeFile($this->container, $fullPathName, $content, false, $check_exist);

            return ['name' => $dest_name, 'path' => $fullPathName, 'type' => 'file'];
        }
    }

    /**
     * @param            $container
     * @param            $path
     * @param            $zip
     * @param bool|false $clean
     * @param null       $drop_path
     *
     * @return array
     */
    public function extractZipFile($container, $path, $zip, $clean = false, $drop_path = null)
    {
        return $this->driver->extractZipFile($container, $path, $zip, $clean, $drop_path);
    }

    /**
     * @param        $dest_path
     * @param        $dest_name
     * @param        $source_file
     * @param string $contentType
     * @param bool   $extract
     * @param bool   $clean
     * @param bool   $check_exist
     *
     * @throws \Exception
     * @return array
     */
    protected function handleFile(
        $dest_path,
        $dest_name,
        $source_file,
        $contentType = '',
        $extract = false,
        $clean = false,
        $check_exist = false
    ){
        $ext = FileUtilities::getFileExtension($source_file);
        if (empty($contentType)) {
            $contentType = FileUtilities::determineContentType($ext, '', $source_file);
        }
        if ((FileUtilities::isZipContent($contentType) || ('zip' === $ext)) && $extract) {
            // need to extract zip file and move contents to storage
            $zip = new \ZipArchive();
            if (true === $zip->open($source_file)) {
                return $this->extractZipFile($this->container, $dest_path, $zip, $clean);
            } else {
                throw new InternalServerErrorException('Error opening temporary zip file.');
            }
        } else {
            $name = (empty($dest_name) ? basename($source_file) : $dest_name);
            $fullPathName = FileUtilities::fixFolderPath($dest_path) . $name;
            $this->driver->moveFile($this->container, $fullPathName, $source_file, $check_exist);

            return ['name' => $name, 'path' => $fullPathName, 'type' => 'file'];
        }
    }

    /**
     * @param array $files
     * @param bool  $extract
     * @param bool  $clean
     * @param bool  $checkExist
     *
     * @return array
     * @throws \Exception
     */
    protected function handleFolderContentFromFiles($files, $extract = false, $clean = false, $checkExist = false)
    {
        $out = [];
        $err = [];
        foreach ($files as $key => $file) {
            $name = $file['name'];
            $error = $file['error'];
            if ($error == UPLOAD_ERR_OK) {
                $tmpName = $file['tmp_name'];

                // Get file's content type
                $contentType = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpName);

                if (empty($contentType)) {
                    // It is not safe to use content-type set by client.
                    // Therefore, only using content-type from client as a fallback.
                    $contentType = $file['type'];
                }
                $tmp = $this->handleFile(
                    $this->folderPath,
                    $name,
                    $tmpName,
                    $contentType,
                    $extract,
                    $clean,
                    $checkExist
                );
                $out[$key] = $tmp;
            } else {
                $err[] = $name;
            }
        }
        if (!empty($err)) {
            $msg = 'Failed to upload the following files to folder ' . $this->folderPath . ': ' . implode(', ', $err);
            throw new InternalServerErrorException($msg);
        }

        return $out;
    }

    /**
     * @param array $data
     * @param bool  $extract
     * @param bool  $clean
     * @param bool  $checkExist
     *
     * @return array
     */
    protected function handleFolderContentFromData(
        $data,
        /** @noinspection PhpUnusedParameterInspection */
        $extract = false,
        /** @noinspection PhpUnusedParameterInspection */
        $clean = false,
        /** @noinspection PhpUnusedParameterInspection */
        $checkExist = false
    ){
        $out = [];
        if (!empty($data) && ArrayUtils::isArrayNumeric($data)) {
            foreach ($data as $key => $resource) {
                switch (ArrayUtils::get($resource, 'type')) {
                    case 'folder':
                        $name = ArrayUtils::get($resource, 'name', '');
                        $srcPath = ArrayUtils::get($resource, 'source_path');
                        if (!empty($srcPath)) {
                            $srcContainer = ArrayUtils::get($resource, 'source_container', $this->container);
                            // copy or move
                            if (empty($name)) {
                                $name = FileUtilities::getNameFromPath($srcPath);
                            }
                            $fullPathName = $this->folderPath . $name . '/';
                            $out[$key] = ['name' => $name, 'path' => $fullPathName, 'type' => 'folder'];
                            try {
                                $this->driver->copyFolder($this->container, $fullPathName, $srcContainer, $srcPath,
                                    true);
                                $deleteSource = ArrayUtils::getBool($resource, 'delete_source');
                                if ($deleteSource) {
                                    $this->driver->deleteFolder($this->container, $srcPath, true);
                                }
                            } catch (\Exception $ex) {
                                $out[$key]['error'] = ['message' => $ex->getMessage()];
                            }
                        } else {
                            $fullPathName = $this->folderPath . $name . '/';
                            $content = ArrayUtils::get($resource, 'content', '');
                            $isBase64 = ArrayUtils::getBool($resource, 'is_base64');
                            if ($isBase64) {
                                $content = base64_decode($content);
                            }
                            $out[$key] = ['name' => $name, 'path' => $fullPathName, 'type' => 'folder'];
                            try {
                                $this->driver->createFolder($this->container, $fullPathName, $content);
                            } catch (\Exception $ex) {
                                $out[$key]['error'] = ['message' => $ex->getMessage()];
                            }
                        }
                        break;
                    case 'file':
                        $name = ArrayUtils::get($resource, 'name', '');
                        $srcPath = ArrayUtils::get($resource, 'source_path');
                        if (!empty($srcPath)) {
                            // copy or move
                            $srcContainer = ArrayUtils::get($resource, 'source_container', $this->container);
                            if (empty($name)) {
                                $name = FileUtilities::getNameFromPath($srcPath);
                            }
                            $fullPathName = $this->folderPath . $name;
                            $out[$key] = ['name' => $name, 'path' => $fullPathName, 'type' => 'file'];
                            try {
                                $this->driver->copyFile($this->container, $fullPathName, $srcContainer, $srcPath, true);
                                $deleteSource = ArrayUtils::getBool($resource, 'delete_source');
                                if ($deleteSource) {
                                    $this->driver->deleteFile($this->container, $srcPath);
                                }
                            } catch (\Exception $ex) {
                                $out[$key]['error'] = ['message' => $ex->getMessage()];
                            }
                        } elseif (isset($resource['content'])) {
                            $fullPathName = $this->folderPath . $name;
                            $out[$key] = ['name' => $name, 'path' => $fullPathName, 'type' => 'file'];
                            $content = ArrayUtils::get($resource, 'content', '');
                            $isBase64 = ArrayUtils::getBool($resource, 'is_base64');
                            if ($isBase64) {
                                $content = base64_decode($content);
                            }
                            try {
                                $this->driver->writeFile($this->container, $fullPathName, $content);
                            } catch (\Exception $ex) {
                                $out[$key]['error'] = ['message' => $ex->getMessage()];
                            }
                        }
                        break;
                }
            }
        }

        return $out;
    }

    /**
     * @param array  $data Array of sub-folder and file paths that are relative to the root folder
     * @param string $root root folder from which to delete
     * @param  bool  $force
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected function deleteFolderContent($data, $root = '', $force = false)
    {
        $root = FileUtilities::fixFolderPath($root);
        $out = [];
        if (!empty($data)) {
            foreach ($data as $key => $resource) {
                $path = ArrayUtils::get($resource, 'path');
                $name = ArrayUtils::get($resource, 'name');

                if (!empty($path)) {
                    $fullPath = $path;
                } else if (!empty($name)) {
                    $fullPath = $root . '/' . $name;
                } else {
                    throw new BadRequestException('No path or name provided for resource.');
                }

                switch (ArrayUtils::get($resource, 'type')) {
                    case 'file':
                        $out[$key] = ['name' => $name, 'path' => $path, 'type' => 'file'];
                        try {
                            $this->driver->deleteFile($this->container, $fullPath);
                        } catch (\Exception $ex) {
                            $out[$key]['error'] = ['message' => $ex->getMessage()];
                        }
                        break;
                    case 'folder':
                        $out[$key] = ['name' => $name, 'path' => $path, 'type' => 'folder'];
                        try {
                            $this->driver->deleteFolder($this->container, $fullPath, $force);
                        } catch (\Exception $ex) {
                            $out[$key]['error'] = ['message' => $ex->getMessage()];
                        }
                        break;
                }
            }
        }

        return $out;
    }

    /**
     * @return string
     */
    public function getContainerId()
    {
        return $this->container;
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * @return string
     */
    public function getFolderPath()
    {
        return $this->folderPath;
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->name;
        $eventPath = $this->name;
        $commonResponses = ApiDocUtilities::getCommonResponses();
        $base = parent::getApiDocInfo();

        $base['apis'] = [
            [
                'path'        => $path,
                'description' => 'Operations available for File Storage Service.',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getResourceList() - List all resource names.',
                        'nickname'         => 'getResourceList',
                        'notes'            => 'Return only a list of the resource identifiers.',
                        'type'             => 'ResourceList',
                        'event_name'       => [$this->name . '.list'],
                        'parameters'       => [
                            ApiOptions::documentOption(ApiOptions::AS_LIST, true, true),
                            ApiOptions::documentOption(ApiOptions::AS_ACCESS_LIST),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::REFRESH),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'getResources() - List all resources.',
                        'nickname'         => 'getResources',
                        'type'             => 'ResourceList',
                        'event_name'       => [$eventPath . '.list',],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'List the resources (folders and files) available in this storage. ',
                        'parameters'       => [
                            [
                                'name'          => 'include_folders',
                                'description'   => 'Include folders in the returned listing.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => true,
                            ],
                            [
                                'name'          => 'include_files',
                                'description'   => 'Include files in the returned listing.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => true,
                            ],
                            [
                                'name'          => 'full_tree',
                                'description'   => 'List the contents of all sub-folders as well.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                            [
                                'name'          => 'zip',
                                'description'   => 'Return the content of the path as a zip file.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'path'        => $path . '/{folder_path}/',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getFolder() - List the folder\'s content, including properties.',
                        'nickname'         => 'getFolder',
                        'type'             => 'FolderResponse',
                        'event_name'       => [$eventPath . '.{folder_path}.describe'],
                        'parameters'       => [
                            [
                                'name'          => 'folder_path',
                                'description'   => 'The path of the folder you want to retrieve. This can be a sub-folder, with each level separated by a \'/\'',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'include_properties',
                                'description'   => 'Return any properties of the folder in the response.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                            [
                                'name'          => 'include_folders',
                                'description'   => 'Include folders in the returned listing.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => true,
                            ],
                            [
                                'name'          => 'include_files',
                                'description'   => 'Include files in the returned listing.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => true,
                            ],
                            [
                                'name'          => 'full_tree',
                                'description'   => 'List the contents of all sub-folders as well.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                            [
                                'name'          => 'zip',
                                'description'   => 'Return the content of the folder as a zip file.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            =>
                            'Use \'include_properties\' to get properties of the folder. ' .
                            'Use the \'include_folders\' and/or \'include_files\' to modify the listing.',
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'getFolderProperties() - List the folder\'s properties.',
                        'nickname'         => 'getFolderProperties',
                        'type'             => 'Folder',
                        'event_name'       => [$eventPath . '.{folder_path}.describe'],
                        'parameters'       => [
                            [
                                'name'          => 'folder_path',
                                'description'   => 'The path of the folder you want to retrieve. This can be a sub-folder, with each level separated by a \'/\'',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'include_properties',
                                'description'   => 'Return any properties of the folder in the response.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => true,
                                'defaultValue'  => true,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            => 'Use \'include_properties\' to get properties of the folder.',
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'createFolder() - Create a folder and/or add content.',
                        'nickname'         => 'createFolder',
                        'type'             => 'FolderResponse',
                        'event_name'       => [
                            $eventPath . '.{folder_path}.create',
                            $eventPath . '.folder_created'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'folder_path',
                                'description'   => 'The path of the folder where you want to put the contents. This can be a sub-folder, with each level separated by a \'/\'',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Array of folders and/or files.',
                                'allowMultiple' => false,
                                'type'          => 'FolderRequest',
                                'paramType'     => 'body',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'url',
                                'description'   => 'The full URL of the file to upload.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'extract',
                                'description'   => 'Extract an uploaded zip file into the folder.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                            [
                                'name'          => 'clean',
                                'description'   => 'Option when \'extract\' is true, clean the current folder before extracting files and folders.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                            [
                                'name'          => 'check_exist',
                                'description'   => 'If true, the request fails when the file or folder to create already exists.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                            [
                                'name'          => 'X-HTTP-METHOD',
                                'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
                                'enum'          => ['GET', 'PUT', 'PATCH', 'DELETE'],
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'header',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            => 'Post data as an array of folders and/or files. Folders are created if they do not exist',
                    ],
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'updateFolderProperties() - Update folder properties.',
                        'nickname'         => 'updateFolderProperties',
                        'type'             => 'Folder',
                        'event_name'       => [
                            $eventPath . '.{folder_path}.update',
                            $eventPath . '.folder_updated'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'folder_path',
                                'description'   => 'The path of the folder you want to update. This can be a sub-folder, with each level separated by a \'/\'',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Array of folder properties.',
                                'allowMultiple' => false,
                                'type'          => 'Folder',
                                'paramType'     => 'body',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            => 'Post body as an array of folder properties.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteFolder() - Delete one folder and/or its contents.',
                        'nickname'         => 'deleteFolder',
                        'type'             => 'FolderResponse',
                        'event_name'       => [
                            $eventPath . '.{folder_path}.delete',
                            $eventPath . '.folder_deleted'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'folder_path',
                                'description'   => 'The path of the folder where you want to delete contents. This can be a sub-folder, with each level separated by a \'/\'',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'force',
                                'description'   => 'Set to true to force delete on a non-empty folder.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'content_only',
                                'description'   => 'Set to true to only delete the content of the folder.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            =>
                            'Set \'content_only\' to true to delete the sub-folders and files contained, but not the folder. ' .
                            'Set \'force\' to true to delete a non-empty folder. ' .
                            'Alternatively, to delete by a listing of sub-folders and files, ' .
                            'use the POST request with X-HTTP-METHOD = DELETE header and post listing.',
                    ],
                ],
                'description' => 'Operations on folders.',
            ],
            [
                'path'        => $path . '/{file_path}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getFile() - Download the file contents and/or its properties.',
                        'nickname'         => 'getFile',
                        'type'             => 'FileResponse',
                        'event_name'       => [
                            $eventPath . '.{file_path}.download',
                            $eventPath . '.file_downloaded'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'file_path',
                                'description'   => 'Path and name of the file to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'download',
                                'description'   => 'Prompt the user to download the file from the browser.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            =>
                            'By default, the file is streamed to the browser. ' .
                            'Use the \'download\' parameter to prompt for download.',
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'getFileProperties() - Download the file properties.',
                        'nickname'         => 'getFileProperties',
                        'type'             => 'File',
                        'event_name'       => [
                            $eventPath . '.{file_path}.describe',
                            $eventPath . '.file_described'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'file_path',
                                'description'   => 'Path and name of the file to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'include_properties',
                                'description'   => 'Return properties of the file.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                            [
                                'name'          => 'content',
                                'description'   => 'Return the content as base64 of the file, only applies when \'include_properties\' is true.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'defaultValue'  => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            =>
                            'Use the \'include_properties\' parameter (optionally add \'content\' to include base64 content) to list properties of the file.',
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'createFile() - Create a new file.',
                        'nickname'         => 'createFile',
                        'type'             => 'FileResponse',
                        'event_name'       => [
                            $eventPath . '.{file_path}.create',
                            $eventPath . '.file_created'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'file_path',
                                'description'   => 'Path and name of the file to create.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'check_exist',
                                'description'   => 'If true, the request fails when the file to create already exists.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Content and/or properties of the file.',
                                'allowMultiple' => false,
                                'type'          => 'FileRequest',
                                'paramType'     => 'body',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            => 'Post body should be the contents of the file or an object with file properties.',
                    ],
                    [
                        'method'           => 'PUT',
                        'summary'          => 'replaceFile() - Update content of the file.',
                        'nickname'         => 'replaceFile',
                        'type'             => 'FileResponse',
                        'event_name'       => [
                            $eventPath . '.{file_path}.update',
                            $eventPath . '.file_updated'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'file_path',
                                'description'   => 'Path and name of the file to update.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'The content of the file.',
                                'allowMultiple' => false,
                                'type'          => 'FileRequest',
                                'paramType'     => 'body',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            => 'Post body should be the contents of the file.',
                    ],
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'updateFileProperties() - Update properties of the file.',
                        'nickname'         => 'updateFileProperties',
                        'type'             => 'File',
                        'event_name'       => [
                            $eventPath . '.{file_path}.update',
                            $eventPath . '.file_updated'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'file_path',
                                'description'   => 'Path and name of the file to update.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Properties of the file.',
                                'allowMultiple' => false,
                                'type'          => 'File',
                                'paramType'     => 'body',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            => 'Post body should be an array of file properties.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteFile() - Delete one file.',
                        'nickname'         => 'deleteFile',
                        'type'             => 'FileResponse',
                        'event_name'       => [
                            $eventPath . '.{file_path}.delete',
                            $eventPath . '.file_deleted'
                        ],
                        'parameters'       => [
                            [
                                'name'          => 'file_path',
                                'description'   => 'Path and name of the file to delete.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $commonResponses,
                        'notes'            => 'Careful, this removes the given file from the storage.',
                    ],
                ],
                'description' => 'Operations on individual files.',
            ],
        ];

        $commonFolder = [
            'name'     => [
                'type'        => 'string',
                'description' => 'Identifier/Name for the folder, localized to requested resource.',
            ],
            'path'     => [
                'type'        => 'string',
                'description' => 'Full path of the folder, from the service root.',
            ],
            'metadata' => [
                'type'        => 'array',
                'description' => 'An array of name-value pairs.',
                'items'       => [
                    'type' => 'string',
                ],
            ],
        ];

        $commonFile = [
            'name'         => [
                'type'        => 'string',
                'description' => 'Identifier/Name for the file, localized to requested resource.',
            ],
            'path'         => [
                'type'        => 'string',
                'description' => 'Full path of the file, from the service root.',
            ],
            'content_type' => [
                'type'        => 'string',
                'description' => 'The media type of the content of the file.',
            ],
            'metadata'     => [
                'type'        => 'array',
                'description' => 'An array of name-value pairs.',
                'items'       => [
                    'type' => 'string',
                ],
            ],
        ];

        $models = [
            'FileRequest'    => [
                'id'         => 'FileRequest',
                'properties' => $commonFile,
            ],
            'FileResponse'   => [
                'id'         => 'FileResponse',
                'properties' => array_merge(
                    $commonFile,
                    [
                        'content_length' => [
                            'type'        => 'string',
                            'description' => 'Size of the file in bytes.',
                        ],
                        'last_modified'  => [
                            'type'        => 'string',
                            'description' => 'A GMT date timestamp of when the file was last modified.',
                        ],
                    ]
                ),
            ],
            'FolderRequest'  => [
                'id'         => 'FolderRequest',
                'properties' => array_merge(
                    $commonFolder,
                    [
                        'resource' => [
                            'type'        => 'array',
                            'description' => 'An array of resources to operate on.',
                            'items'       => [
                                '$ref' => 'ResourceRequest',
                            ],
                        ],
                    ]
                ),
            ],
            'FolderResponse' => [
                'id'         => 'FolderResponse',
                'properties' => array_merge(
                    $commonFolder,
                    [
                        'last_modified' => [
                            'type'        => 'string',
                            'description' => 'A GMT date timestamp of when the file was last modified.',
                        ],
                        'resources'     => [
                            'type'        => 'array',
                            'description' => 'An array of contained resources.',
                            'items'       => [
                                '$ref' => 'FolderResponse',
                            ],
                        ],
                    ]
                ),
            ],
            'File'           => [
                'id'         => 'File',
                'properties' => $commonFile,
            ],
            'Folder'         => [
                'id'         => 'Folder',
                'properties' => $commonFolder,
            ],
        ];

        $base['models'] = array_merge($base['models'], $models);

        return $base;
    }

    /**
     * Runs pre process tasks/scripts
     */
    protected function preProcess()
    {
        // Pre process not supported on file services
        return true;
    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {
        // Post process not supported on file services
        return true;
    }
}