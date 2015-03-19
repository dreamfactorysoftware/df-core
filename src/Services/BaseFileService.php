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

namespace DreamFactory\Rave\Services;

use DreamFactory\Rave\Utility\FileUtilities;
use DreamFactory\Rave\Utility\ResponseFactory;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;

/**
 * Class BaseFileService
 *
 * @package DreamFactory\Rave\Services
 */
abstract class BaseFileService extends BaseRestService
{
    /**
     * @var \DreamFactory\Rave\Contracts\FileSystemInterface
     */
    protected $driver = null;
    /**
     * @var array Array of private path strings
     */
    public $privatePaths = array();

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

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        $verbAliases = array(
            Verbs::PUT   => Verbs::POST,
            Verbs::MERGE => Verbs::PATCH
        );
        ArrayUtils::set( $settings, "verbAliases", $verbAliases );
        parent::__construct( $settings );
        $this->setDriver( ArrayUtils::get( $settings, 'config' ) );
    }

    /**
     * Sets the file system driver Local/S3/Azure/OStack...
     *
     * @param $config
     *
     * @return mixed
     */
    abstract protected function setDriver( $config );

    /**
     * Apply the commonly used REST path members to the class.
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function setResourceMembers( $resourcePath = null )
    {
        parent::setResourceMembers( $resourcePath );

        $this->container = ArrayUtils::get( $this->resourceArray, 0 );

        if ( !empty( $this->container ) )
        {
            $temp = substr( $this->resourcePath, strlen( $this->container . '/' ) );
            if ( false !== $temp )
            {
                if ( $this->hasTrailingSlash( $temp ) )
                {
                    $this->folderPath = $temp;
                }
                else
                {
                    $this->folderPath = dirname( $temp ) . '/';
                    $this->filePath = $temp;
                }
            }
        }

        return $this;
    }

    /**
     * @return bool|mixed
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    protected function handleResource()
    {
        //  Fall through is to process just like a no-resource request
        $resources = $this->getResources();
        if ( ( false !== $resources ) && !empty( $this->resource ) )
        {
            if ( in_array( $this->resource, $resources ) )
            {
                return $this->processRequest();
            }
        }

        throw new NotFoundException( "Resource '{$this->resource}' not found for service '{$this->name}'." );
    }

    /**
     * Handles GET actions.
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     */
    protected function handleGET()
    {
        if ( empty( $this->container ) )
        {
            $result = $this->handleGetResource();
        }
        elseif ( empty( $this->folderPath ) )
        {
            //Resource is a container
            $result = $this->handleGetContainer();
        }
        elseif ( empty( $this->filePath ) )
        {
            //Resource is a folder
            $result = $this->handleGetFolder();
        }
        else
        {
            //Resource is a file
            $result = $this->handleGetFile();
        }

        return $result;
    }

    /**
     * Handles POST actions.
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws \Exception
     */
    protected function handlePOST()
    {
        if ( empty( $this->container ) )
        {
            // create one or more containers
            $checkExist = $this->getQueryBool( 'check_exist', false );
            $data = $this->getPayloadData();
            $containers = ArrayUtils::get( $data, 'container' );

            if ( empty( $containers ) )
            {
                $containers = ArrayUtils::getDeep( $data, 'containers', 'container' );
            }

            if ( !empty( $containers ) )
            {
                $result = $this->driver->createContainers( $containers, $checkExist );
                $result = array( 'container' => $result );
            }
            else
            {
                $result = $this->driver->createContainer( $data, $checkExist );
            }
        }
        else if ( empty( $this->folderPath ) || empty( $this->filePath ) )
        {
            // create folders and files
            // possible file handling parameters
            $extract = $this->getQueryBool( 'extract', false );;
            $clean = $this->getQueryBool( 'clean', false );
            $checkExist = $this->getQueryBool( 'check_exist', false );

            $fileNameHeader = $this->request->getHeader( 'HTTP_X_FILE_NAME' );
            $folderNameHeader = $this->request->getHeader( 'HTTP_X_FOLDER_NAME' );
            $fileUrl = filter_var( $this->getQueryData( 'url', '' ), FILTER_SANITIZE_URL );

            if ( !empty( $fileNameHeader ) )
            {
                // html5 single posting for file create
                $content = $this->getPayloadData();
                $contentType = $this->request->getHeader( 'CONTENT_TYPE', '' );
                $result = $this->handleFileContent(
                    $this->folderPath,
                    $fileNameHeader,
                    $content,
                    $contentType,
                    $extract,
                    $clean,
                    $checkExist
                );
            }
            elseif ( !empty( $folderNameHeader ) )
            {
                // html5 single posting for folder create
                $fullPathName = $this->folderPath . $folderNameHeader;
                $content = $this->getPayloadData();
                $this->driver->createFolder( $this->container, $fullPathName, $content );
                $result = array(
                    'folder' => array(
                        array(
                            'name' => $folderNameHeader,
                            'path' => $this->container . '/' . $fullPathName
                        )
                    )
                );
            }
            elseif ( !empty( $fileUrl ) )
            {
                // upload a file from a url, could be expandable zip
                $tmpName = null;
                try
                {
                    $tmpName = FileUtilities::importUrlFileToTemp( $fileUrl );
                    $result = $this->handleFile(
                        $this->folderPath,
                        '',
                        $tmpName,
                        '',
                        $extract,
                        $clean,
                        $checkExist
                    );
                    @unlink( $tmpName );
                }
                catch ( \Exception $ex )
                {
                    if ( !empty( $tmpName ) )
                    {
                        @unlink( $tmpName );
                    }
                    throw $ex;
                }
            }
            elseif ( null !== $uploadedFiles = $this->request->getFile( 'files' ) )
            {
                // older html multi-part/form-data post, single or multiple files
                $files = FileUtilities::rearrangePostedFiles( $uploadedFiles );
                $result = $this->handleFolderContentFromFiles( $files, $extract, $clean, $checkExist );
            }
            else
            {
                // possibly xml or json post either of files or folders to create, copy or move
                $data = $this->getPayloadData();
                if ( empty( $data ) )
                {
                    // create folder from resource path
                    $this->driver->createFolder( $this->container, $this->folderPath );
                    $result = array( 'folder' => array( array( 'path' => $this->container . '/' . $this->folderPath ) ) );
                }
                else
                {
                    $result = $this->handleFolderContentFromData( $data, $extract, $clean, $checkExist );
                }
            }
        }
        else
        {
            // create the file
            // possible file handling parameters
            $extract = $this->getQueryBool( 'extract', false );
            $clean = $this->getQueryBool( 'clean', false );
            $checkExist = $this->getQueryBool( 'check_exist', false );
            $name = basename( $this->filePath );
            $path = dirname( $this->filePath );
            $files = $this->request->getFile( 'files' );
            if ( empty( $files ) )
            {
                $contentType = $this->request->getHeader( 'CONTENT_TYPE', '' );
                // direct load from posted data as content
                // or possibly xml or json post of file properties create, copy or move
                $content = $this->getPayloadData();
                $result = $this->handleFileContent(
                    $path,
                    $name,
                    $content,
                    $contentType,
                    $extract,
                    $clean,
                    $checkExist
                );
            }
            else
            {
                // older html multipart/form-data post, should be single file
                $files = FileUtilities::rearrangePostedFiles( $files );
                if ( 1 < count( $files ) )
                {
                    throw new BadRequestException( "Multiple files uploaded to a single REST resource '$name'." );
                }
                $file = ArrayUtils::get( $files, 0 );
                if ( empty( $file ) )
                {
                    throw new BadRequestException( "No file uploaded to REST resource '$name'." );
                }
                $error = $file['error'];
                if ( UPLOAD_ERR_OK == $error )
                {
                    $tmpName = $file["tmp_name"];
                    $contentType = $file['type'];
                    $result = $this->handleFile(
                        $path,
                        $name,
                        $tmpName,
                        $contentType,
                        $extract,
                        $clean,
                        $checkExist
                    );
                }
                else
                {
                    throw new InternalServerErrorException( "Failed to upload file $name.\n$error" );
                }
            }
        }

        return ResponseFactory::create( $result, $this->outputFormat, ServiceResponseInterface::HTTP_CREATED );
    }

    /**
     * Handles PATCH actions.
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     */
    protected function handlePATCH()
    {
        if ( empty( $this->container ) )
        {
            // nothing?
            $result = array();
        }
        else if ( empty( $this->folderPath ) )
        {
            // update container properties
            $content = $this->getPayloadData();
            $this->driver->updateContainerProperties( $this->container, $content );
            $result = array( 'container' => array( 'name' => $this->container ) );
        }
        else if ( empty( $this->filePath ) )
        {
            // update folder properties
            $content = $this->getPayloadData();
            $this->driver->updateFolderProperties( $this->container, $this->folderPath, $content );
            $result = array(
                'folder' => array(
                    'name' => basename( $this->folderPath ),
                    'path' => $this->container . '/' . $this->folderPath
                )
            );
        }
        else
        {
            // update file properties?
            $content = $this->getPayloadData();
            $this->driver->updateFileProperties( $this->container, $this->filePath, $content );
            $result = array(
                'file' => array(
                    'name' => basename( $this->filePath ),
                    'path' => $this->container . '/' . $this->filePath
                )
            );
        }

        return $result;
    }

    /**
     * Handles DELETE actions.
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     */
    protected function handleDELETE()
    {
        $force = $this->getQueryBool( 'force', false );
        $content = $this->getPayloadData();

        if ( empty( $this->container ) )
        {
            $containers = ArrayUtils::get( $content, 'container' );
            if ( empty( $containers ) )
            {
                $containers = ArrayUtils::getDeep( $content, 'containers', 'container' );
            }

            if ( empty( $containers ) )
            {
                $namesStr = $this->getQueryData( 'names', '' );

                if ( !empty( $namesStr ) )
                {
                    $names = explode( ',', $namesStr );

                    foreach ( $names as $n )
                    {
                        $containers[] = array( "name" => $n );
                    }
                }
            }

            if ( !empty( $containers ) )
            {
                // delete multiple containers
                $result = $this->driver->deleteContainers( $containers, $force );
                $result = array( 'container' => $result );
            }
            else
            {
                $_name = ArrayUtils::get( $content, 'name', trim( ArrayUtils::get( $content, 'path' ), '/' ) );
                if ( empty( $_name ) )
                {
                    throw new BadRequestException( 'No name found for container in delete request.' );
                }
                $this->driver->deleteContainer( $_name, $force );
                $result = array( 'name' => $_name, 'path' => $_name );
            }
        }
        else if ( empty( $this->folderPath ) )
        {
            // delete whole container
            // or just folders and files from the container
            if ( empty( $content ) )
            {
                $this->driver->deleteContainer( $this->container, $force );
                $result = array( 'name' => $this->container );
            }
            else
            {
                $result = $this->deleteFolderContent( $content, '', $force );
            }
        }
        else if ( empty( $this->filePath ) )
        {
            // delete directory of files and the directory itself
            // multi-file or folder delete via post data
            if ( empty( $content ) )
            {
                $this->driver->deleteFolder( $this->container, $this->folderPath, $force );
                $result = array( 'folder' => array( array( 'path' => $this->container . '/' . $this->folderPath ) ) );
            }
            else
            {
                $result = $this->deleteFolderContent( $content, $this->folderPath, $force );
            }
        }
        else
        {
            // delete file from permanent storage
            $this->driver->deleteFile( $this->container, $this->filePath );
            $result = array( 'file' => array( array( 'path' => $this->container . '/' . $this->filePath ) ) );
        }

        return $result;
    }

    /**
     * Handles getting resource/container list
     *
     * @return array
     */
    protected function handleGetResource()
    {
        $includeProperties = $this->getQueryBool( 'include_properties', false );
        $asAccessComp = $this->getQueryBool( 'as_access_components' );

        if ( $asAccessComp )
        {
            $result = array( "resource" => array_merge( array( "", "*" ), $this->getResources() ) );
        }
        else
        {
            $result = $this->driver->listContainers( $includeProperties );

            if ( $includeProperties )
            {
                $result = array( "container" => $result );
            }
            else
            {
                $result = array( "resource" => $result );
            }
        }

        return $result;
    }

    /**
     * Handles getting list of folders inside a container
     *
     * @return array|null
     */
    protected function handleGetContainer()
    {
        $includeProperties = $this->getQueryBool( 'include_properties', false );
        $includeFolders = $this->getQueryBool( 'include_folders', true );
        $includeFiles = $this->getQueryBool( 'include_files', true );
        $fullTree = $this->getQueryBool( 'full_tree', false );
        $asZip = $this->getQueryBool( 'zip' );

        if ( $asZip )
        {
            $zipFileName = $this->driver->getFolderAsZip( $this->container, '' );
            FileUtilities::sendFile( $zipFileName, true );
            unlink( $zipFileName );

            // output handled by file handler, short the response here
            $this->setResponseFormat( null );
            $result = null;
        }
        else
        {
            $result = $this->driver->getContainer(
                $this->container,
                $includeFiles,
                $includeFolders,
                $fullTree,
                $includeProperties
            );
        }

        return $result;
    }

    /**
     * Handles getting list of folders/files inside a folder
     *
     * @return array|null
     */
    protected function handleGetFolder()
    {
        $includeProperties = $this->getQueryBool( 'include_properties', false );
        $includeFolders = $this->getQueryBool( 'include_folders', true );
        $includeFiles = $this->getQueryBool( 'include_files', true );
        $fullTree = $this->getQueryBool( 'full_tree', false );
        $asZip = $this->getQueryBool( 'zip' );

        if ( $asZip )
        {
            $zipFileName = $this->driver->getFolderAsZip( $this->container, $this->folderPath );
            FileUtilities::sendFile( $zipFileName, true );
            unlink( $zipFileName );

            // output handled by file handler, short the response here
            $this->setResponseFormat( null );
            $result = null;
        }
        else
        {
            $result = $this->driver->getFolder(
                $this->container,
                $this->folderPath,
                $includeFiles,
                $includeFolders,
                $fullTree,
                $includeProperties
            );
        }

        return $result;
    }

    /**
     * Handles getting a file
     *
     * @return array|null
     */
    protected function handleGetFile()
    {
        $includeProperties = $this->getQueryBool( 'include_properties', false );

        if ( $includeProperties )
        {
            // just properties of the file itself
            $content = $this->getQueryBool( 'content', false );
            $result = $this->driver->getFileProperties( $this->container, $this->filePath, $content );
        }
        else
        {
            $download = $this->getQueryBool( 'download', false );
            // stream the file, exits processing
            $this->driver->streamFile( $this->container, $this->filePath, $download );

            // output handled by file handler, short the response here
            $this->setResponseFormat( null );
            $result = null;
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getResources()
    {
        $containers = $this->driver->listContainers();
        $resources = [ ];

        foreach ( $containers as $container )
        {
            $resources[] = ArrayUtils::get( $container, "name" );
        }

        return $resources;
    }

    /**
     * Checks to see if the path has a trailing slash. This is used for
     * determining whether a path is a folder or file.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function hasTrailingSlash( $path )
    {
        if ( DIRECTORY_SEPARATOR === substr( $path, strlen( $path ) - 1 ) )
        {
            return true;
        }

        return false;
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
    protected function handleFileContent( $dest_path, $dest_name, $content, $contentType = '', $extract = false, $clean = false, $check_exist = false )
    {
        $ext = FileUtilities::getFileExtension( $dest_name );
        if ( empty( $contentType ) )
        {
            $contentType = FileUtilities::determineContentType( $ext, $content );
        }
        if ( ( FileUtilities::isZipContent( $contentType ) || ( 'zip' === $ext ) ) && $extract )
        {
            // need to extract zip file and move contents to storage
            $tempDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
            $tmpName = $tempDir . $dest_name;
            file_put_contents( $tmpName, $content );
            $zip = new \ZipArchive();
            $code = $zip->open( $tmpName );
            if ( true !== $code )
            {
                unlink( $tmpName );

                throw new InternalServerErrorException( 'Error opening temporary zip file. code = ' . $code );
            }

            $results = $this->driver->extractZipFile( $this->container, $dest_path, $zip, $clean );
            unlink( $tmpName );

            return $results;
        }
        else
        {
            $fullPathName = FileUtilities::fixFolderPath( $dest_path ) . $dest_name;
            $this->driver->writeFile( $this->container, $fullPathName, $content, false, $check_exist );

            return array(
                'file' => array(
                    array(
                        'name' => $dest_name,
                        'path' => $this->container . '/' . $fullPathName
                    )
                )
            );
        }
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
    protected function handleFile( $dest_path, $dest_name, $source_file, $contentType = '', $extract = false, $clean = false, $check_exist = false )
    {
        $ext = FileUtilities::getFileExtension( $source_file );
        if ( empty( $contentType ) )
        {
            $contentType = FileUtilities::determineContentType( $ext, '', $source_file );
        }
        if ( ( FileUtilities::isZipContent( $contentType ) || ( 'zip' === $ext ) ) && $extract )
        {
            // need to extract zip file and move contents to storage
            $zip = new \ZipArchive();
            if ( true === $zip->open( $source_file ) )
            {
                return $this->driver->extractZipFile( $this->container, $dest_path, $zip, $clean );
            }
            else
            {
                throw new InternalServerErrorException( 'Error opening temporary zip file.' );
            }
        }
        else
        {
            $name = ( empty( $dest_name ) ? basename( $source_file ) : $dest_name );
            $fullPathName = FileUtilities::fixFolderPath( $dest_path ) . $name;
            $this->driver->moveFile( $this->container, $fullPathName, $source_file, $check_exist );

            return array(
                'file' => array(
                    array(
                        'name' => $name,
                        'path' => $this->container . '/' . $fullPathName
                    )
                )
            );
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
    protected function handleFolderContentFromFiles( $files, $extract = false, $clean = false, $checkExist = false )
    {
        $out = array();
        $err = array();
        foreach ( $files as $key => $file )
        {
            $name = $file['name'];
            $error = $file['error'];
            if ( $error == UPLOAD_ERR_OK )
            {
                $tmpName = $file['tmp_name'];
                $contentType = $file['type'];
                $tmp = $this->handleFile(
                    $this->folderPath,
                    $name,
                    $tmpName,
                    $contentType,
                    $extract,
                    $clean,
                    $checkExist
                );
                $out[$key] = ( isset( $tmp['file'] ) ? $tmp['file'] : array() );
            }
            else
            {
                $err[] = $name;
            }
        }
        if ( !empty( $err ) )
        {
            $msg = 'Failed to upload the following files to folder ' . $this->folderPath . ': ' . implode( ', ', $err );
            throw new InternalServerErrorException( $msg );
        }

        return array( 'file' => $out );
    }

    /**
     * @param array $data
     * @param bool  $extract
     * @param bool  $clean
     * @param bool  $checkExist
     *
     * @return array
     */
    protected function handleFolderContentFromData( $data, $extract = false, $clean = false, $checkExist = false )
    {
        $out = array( 'folder' => array(), 'file' => array() );
        $folders = ArrayUtils::get( $data, 'folder' );
        if ( empty( $folders ) )
        {
            $folders = ArrayUtils::getDeep( $data, 'folders', 'folder' );
        }
        if ( !empty( $folders ) )
        {
            if ( !isset( $folders[0] ) )
            {
                // single folder, make into array
                $folders = array( $folders );
            }
            foreach ( $folders as $key => $folder )
            {
                $name = ArrayUtils::get( $folder, 'name', '' );
                $srcPath = ArrayUtils::get( $folder, 'source_path' );
                if ( !empty( $srcPath ) )
                {
                    $srcContainer = ArrayUtils::get( $folder, 'source_container', $this->container );
                    // copy or move
                    if ( empty( $name ) )
                    {
                        $name = FileUtilities::getNameFromPath( $srcPath );
                    }
                    $fullPathName = $this->folderPath . $name . '/';
                    $out['folder'][$key] = array( 'name' => $name, 'path' => $this->container . '/' . $fullPathName );
                    try
                    {
                        $this->driver->copyFolder( $this->container, $fullPathName, $srcContainer, $srcPath, true );
                        $deleteSource = ArrayUtils::getBool( $folder, 'delete_source' );
                        if ( $deleteSource )
                        {
                            $this->driver->deleteFolder( $this->container, $srcPath, true );
                        }
                    }
                    catch ( \Exception $ex )
                    {
                        $out['folder'][$key]['error'] = array( 'message' => $ex->getMessage() );
                    }
                }
                else
                {
                    $fullPathName = $this->folderPath . $name;
                    $content = ArrayUtils::get( $folder, 'content', '' );
                    $isBase64 = ArrayUtils::getBool( $folder, 'is_base64' );
                    if ( $isBase64 )
                    {
                        $content = base64_decode( $content );
                    }
                    $out['folder'][$key] = array( 'name' => $name, 'path' => $this->container . '/' . $fullPathName );
                    try
                    {
                        $this->driver->createFolder( $this->container, $fullPathName, true, $content );
                    }
                    catch ( \Exception $ex )
                    {
                        $out['folder'][$key]['error'] = array( 'message' => $ex->getMessage() );
                    }
                }
            }
        }
        $files = ArrayUtils::get( $data, 'file' );
        if ( empty( $files ) )
        {
            $files = ArrayUtils::getDeep( $data, 'files', 'file' );
        }
        if ( !empty( $files ) )
        {
            if ( !isset( $files[0] ) )
            {
                // single file, make into array
                $files = array( $files );
            }
            foreach ( $files as $key => $file )
            {
                $name = ArrayUtils::get( $file, 'name', '' );
                $srcPath = ArrayUtils::get( $file, 'source_path' );
                if ( !empty( $srcPath ) )
                {
                    // copy or move
                    $srcContainer = ArrayUtils::get( $file, 'source_container', $this->container );
                    if ( empty( $name ) )
                    {
                        $name = FileUtilities::getNameFromPath( $srcPath );
                    }
                    $fullPathName = $this->folderPath . $name;
                    $out['file'][$key] = array( 'name' => $name, 'path' => $this->container . '/' . $fullPathName );
                    try
                    {
                        $this->driver->copyFile( $this->container, $fullPathName, $srcContainer, $srcPath, true );
                        $deleteSource = ArrayUtils::getBool( $file, 'delete_source' );
                        if ( $deleteSource )
                        {
                            $this->driver->deleteFile( $this->container, $srcPath );
                        }
                    }
                    catch ( \Exception $ex )
                    {
                        $out['file'][$key]['error'] = array( 'message' => $ex->getMessage() );
                    }
                }
                elseif ( isset( $file['content'] ) )
                {
                    $fullPathName = $this->folderPath . $name;
                    $out['file'][$key] = array( 'name' => $name, 'path' => $this->container . '/' . $fullPathName );
                    $content = ArrayUtils::get( $file, 'content', '' );
                    $isBase64 = ArrayUtils::getBool( $file, 'is_base64' );
                    if ( $isBase64 )
                    {
                        $content = base64_decode( $content );
                    }
                    try
                    {
                        $this->driver->writeFile( $this->container, $fullPathName, $content );
                    }
                    catch ( \Exception $ex )
                    {
                        $out['file'][$key]['error'] = array( 'message' => $ex->getMessage() );
                    }
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
     */
    protected function deleteFolderContent( $data, $root = '', $force = false )
    {
        $out = array( 'folder' => array(), 'file' => array() );
        $folders = ArrayUtils::get( $data, 'folder' );
        if ( empty( $folders ) )
        {
            $folders = ArrayUtils::getDeep( $data, 'folders', 'folder' );
        }
        if ( !empty( $folders ) )
        {
            if ( !isset( $folders[0] ) )
            {
                // single folder, make into array
                $folders = array( $folders );
            }
            $out['folder'] = $this->driver->deleteFolders( $this->container, $folders, $root, $force );
        }
        $files = ArrayUtils::get( $data, 'file' );
        if ( empty( $files ) )
        {
            $files = ArrayUtils::getDeep( $data, 'files', 'file' );
        }
        if ( !empty( $files ) )
        {
            if ( !isset( $files[0] ) )
            {
                // single file, make into array
                $files = array( $files );
            }
            $out['files'] = $this->driver->deleteFiles( $this->container, $files, $root );
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
}