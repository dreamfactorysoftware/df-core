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

namespace DreamFactory\Rave\Components;

use DreamFactory\Rave\Utility\FileUtilities;
use DreamFactory\Rave\Contracts\FileSystemInterface;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;

/**
 * Class LocalFileSystem
 *
 * @package DreamFactory\Rave\Components
 */
class LocalFileSystem implements FileSystemInterface
{
    /**
     * @var string File System root.
     */
    protected static $root = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    public function __construct( $root )
    {
        if ( empty( $root ) )
        {
            throw new InternalServerErrorException( "Invalid root supplied for local file system." );
        }

        static::$root = $root;
    }

    /**
     * Creates the container for this file management if it does not already exist
     *
     * @param string $container
     *
     * @throws \Exception
     */
    public function checkContainerForWrite( $container )
    {
        $container = static::addContainerToName( $container, '' );
        if ( !is_dir( $container ) )
        {
            if ( !mkdir( $container, 0777, true ) )
            {
                throw new InternalServerErrorException( 'Failed to create container.' );
            }
        }
    }

    /**
     * List all containers, just names if noted
     *
     * @param bool $include_properties If true, additional properties are retrieved
     *
     * @throws \Exception
     * @return array
     */
    public function listContainers( $include_properties = false )
    {
        $_out = array();
        $_root = FileUtilities::fixFolderPath( static::asFullPath( '', false ) );
        $_files = array_diff( scandir( $_root ), array( '.', '..', '.private' ) );
        foreach ( $_files as $_file )
        {
            $_dir = $_root . $_file;
            // get file meta
            if ( is_dir( $_dir ) )
            {
                $_result = array( 'name' => $_file, 'path' => $_file );
                if ( $include_properties )
                {
                    $_temp = stat( $_dir );
                    $_result['last_modified'] = gmdate( static::TIMESTAMP_FORMAT, ArrayUtils::get( $_temp, 'mtime', 0 ) );
                }

                $_out[] = $_result;
            }
        }

        return $_out;
    }

    /**
     * Check if a container exists
     *
     * @param  string $container Container name
     *
     * @return boolean
     */
    public function containerExists( $container )
    {
        $_dir = static::addContainerToName( $container, '' );

        return is_dir( $_dir );
    }

    /**
     * Gets all properties of a particular container, if options are false,
     * otherwise include content from the container
     *
     * @param string $container Container name
     * @param bool   $include_files
     * @param bool   $include_folders
     * @param bool   $full_tree
     * @param bool   $include_properties
     *
     * @return array
     */
    public function getContainer( $container, $include_files = true, $include_folders = true, $full_tree = false, $include_properties = false )
    {
        return $this->getFolder( $container, '', $include_files, $include_folders, $full_tree, $include_properties );
    }

    /**
     * Create a container using properties, where at least name is required
     *
     * @param array $properties
     * @param bool  $check_exist If true, throws error if the container already exists
     *
     * @throws \Exception
     * @throws BadRequestException
     * @return array
     */
    public function createContainer( $properties = array(), $check_exist = false )
    {
        $_container = ArrayUtils::get( $properties, 'name', ArrayUtils::get( $properties, 'path' ) );
        if ( empty( $_container ) )
        {
            throw new BadRequestException( 'No name found for container in create request.' );
        }
        // does this folder already exist?
        if ( $this->folderExists( $_container, '' ) )
        {
            if ( $check_exist )
            {
                throw new BadRequestException( "Container '$_container' already exists." );
            }
        }
        else
        {
            // create the container
            $_dir = static::addContainerToName( $_container, '' );

            if ( !mkdir( $_dir, 0777, true ) )
            {
                throw new InternalServerErrorException( 'Failed to create container.' );
            }
        }

        return array( 'name' => $_container, 'path' => $_container );

//            $properties = (empty($properties)) ? '' : json_encode($properties);
//            $result = file_put_contents($key, $properties);
//            if (false === $result) {
//                throw new InternalServerErrorException('Failed to create container properties.');
//            }
    }

    /**
     * Create multiple containers using array of properties, where at least name is required
     *
     * @param array $containers
     * @param bool  $check_exist If true, throws error if the container already exists
     *
     * @return array
     */
    public function createContainers( $containers = array(), $check_exist = false )
    {
        if ( empty( $containers ) )
        {
            return array();
        }

        $_out = array();
        foreach ( $containers as $_key => $_folder )
        {
            try
            {
                // path is full path, name is relative to root, take either
                $_out[$_key] = $this->createContainer( $_folder, $check_exist );
            }
            catch ( \Exception $ex )
            {
                // error whole batch here?
                $_out[$_key]['error'] = array( 'message' => $ex->getMessage(), 'code' => $ex->getCode() );
            }
        }

        return $_out;
    }

    /**
     * Update a container with some properties
     *
     * @param string $container
     * @param array  $properties
     *
     * @throws NotFoundException
     * @return void
     */
    public function updateContainerProperties( $container, $properties = array() )
    {
        // does this folder exist?
        if ( !$this->folderExists( $container, '' ) )
        {
            throw new NotFoundException( "Container '$container' does not exist." );
        }
        // update the file that holds folder properties
//            $properties = json_encode($properties);
//            $key = static::addContainerToName($container, '');
//            $result = file_put_contents($key, $properties);
//            if (false === $result) {
//                throw InternalServerErrorException('Failed to create container properties.');
//            }
    }

    /**
     * Delete a container and all of its content
     *
     * @param string $container
     * @param bool   $force Force a delete if it is not empty
     *
     * @throws \Exception
     * @return void
     */
    public function deleteContainer( $container, $force = false )
    {
        $_dir = static::addContainerToName( $container, '' );
        if ( !rmdir( $_dir ) )
        {
            throw new InternalServerErrorException( 'Failed to delete container.' );
        }
    }

    /**
     * Delete multiple containers and all of their content
     *
     * @param array $containers
     * @param bool  $force Force a delete if it is not empty
     *
     * @throws BadRequestException
     * @return array
     */
    public function deleteContainers( $containers, $force = false )
    {
        if ( !empty( $containers ) )
        {
            if ( !isset( $containers[0] ) )
            {
                // single folder, make into array
                $containers = array( $containers );
            }
            foreach ( $containers as $_key => $_folder )
            {
                try
                {
                    // path is full path, name is relative to root, take either
                    $_name = ArrayUtils::get( $_folder, 'name', trim( ArrayUtils::get( $_folder, 'path' ), '/' ) );
                    if ( !empty( $_name ) )
                    {
                        $this->deleteContainer( $_name, $force );
                    }
                    else
                    {
                        throw new BadRequestException( 'No name found for container in delete request.' );
                    }
                }
                catch ( \Exception $ex )
                {
                    // error whole batch here?
                    $containers[$_key]['error'] = array( 'message' => $ex->getMessage(), 'code' => $ex->getCode() );
                }
            }
        }

        return $containers;
    }

    /**
     * @param $container
     * @param $path
     *
     * @return bool
     */
    public function folderExists( $container, $path )
    {
        $path = FileUtilities::fixFolderPath( $path );
        $_dir = static::addContainerToName( $container, $path );

        return is_dir( $_dir );
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $include_files
     * @param bool   $include_folders
     * @param bool   $full_tree
     * @param bool   $include_properties
     *
     * @throws NotFoundException
     * @return array
     */
    public function getFolder( $container, $path, $include_files = true, $include_folders = true, $full_tree = false, $include_properties = false )
    {
        $path = FileUtilities::fixFolderPath( $path );

        $_out = array(
            'container' => $container,
            'name'      => empty( $path ) ? $container : basename( $path ),
            'path'      => empty( $path ) ? $container : $container . '/' . $path,
        );

        if ( $include_properties )
        {
            $_dirPath = static::addContainerToName( $container, $path );
            $_temp = stat( $_dirPath );
            $_out['last_modified'] = gmdate( static::TIMESTAMP_FORMAT, ArrayUtils::get( $_temp, 'mtime', 0 ) );
        }

        $_delimiter = ( $full_tree ) ? '' : DIRECTORY_SEPARATOR;
        $_files = array();
        $_folders = array();
        $_dirPath = FileUtilities::fixFolderPath( static::asFullPath( '' ) );
        if ( is_dir( $_dirPath ) )
        {
            $_localizer = $container . '/' . $path;
            $_results = static::listTree( $_dirPath, $container . '/' . $path, $_delimiter );
            foreach ( $_results as $_data )
            {
                $_fullPathName = $_data['path'];
                $_data['name'] = rtrim( substr( $_fullPathName, strlen( $_localizer ) ), '/' );
                if ( '/' == substr( $_fullPathName, -1, 1 ) )
                {
                    // folders
                    if ( $include_folders )
                    {
                        $_folders[] = $_data;
                    }
                }
                else
                {
                    // files
                    if ( $include_files )
                    {
                        $_files[] = $_data;
                    }
                }
            }
        }
        else
        {
            if ( !empty( $path ) )
            {
                throw new NotFoundException( "Folder '$path' does not exist in storage." );
            }
            // container root doesn't really exist until first write creates it
        }

        $_out['folder'] = $_folders;
        $_out['file'] = $_files;

        return $_out;
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $is_public
     * @param array  $properties
     * @param bool   $check_exist
     *
     * @throws \Exception
     * @throws NotFoundException
     * @throws BadRequestException
     * @return void
     */
    public function createFolder( $container, $path, $is_public = true, $properties = array(), $check_exist = true )
    {
        if ( empty( $path ) )
        {
            throw new BadRequestException( "Invalid empty path." );
        }
        $path = FileUtilities::fixFolderPath( $path );

        // does this folder already exist?
        if ( $this->folderExists( $container, $path ) )
        {
            if ( $check_exist )
            {
                throw new BadRequestException( "Folder '$path' already exists." );
            }

            return;
        }

        // create the folder
        $this->checkContainerForWrite( $container ); // need to be able to write to storage
        $_dir = static::addContainerToName( $container, $path );

        if ( false === @mkdir( $_dir, 0777, true ) )
        {
            Log::error( 'Unable to create directory: ' . $_dir );
            throw new InternalServerErrorException( 'Failed to create folder: ' . $path );
        }
//            $properties = (empty($properties)) ? '' : json_encode($properties);
//            $result = file_put_contents($key, $properties);
//            if (false === $result) {
//                throw new InternalServerErrorException('Failed to create folder properties.');
//            }
    }

    /**
     * @param string $container
     * @param string $dest_path
     * @param        $src_container
     * @param string $src_path
     * @param bool   $check_exist
     *
     * @throws NotFoundException
     * @throws BadRequestException
     * @return void
     */
    public function copyFolder( $container, $dest_path, $src_container, $src_path, $check_exist = false )
    {
        // does this file already exist?
        if ( !$this->folderExists( $src_container, $src_path ) )
        {
            throw new NotFoundException( "Folder '$src_path' does not exist." );
        }
        if ( $this->folderExists( $container, $dest_path ) )
        {
            if ( ( $check_exist ) )
            {
                throw new BadRequestException( "Folder '$dest_path' already exists." );
            }
        }
        // does this file's parent folder exist?
        $parent = FileUtilities::getParentFolder( $dest_path );
        if ( !empty( $parent ) && ( !$this->folderExists( $container, $parent ) ) )
        {
            throw new NotFoundException( "Folder '$parent' does not exist." );
        }
        // create the folder
        $this->checkContainerForWrite( $container ); // need to be able to write to storage
        FileUtilities::copyTree(
            static::addContainerToName( $src_container, $src_path ),
            static::addContainerToName( $container, $dest_path )
        );
    }

    /**
     * @param string $container
     * @param string $path
     * @param array  $properties
     *
     * @throws NotFoundException
     * @return void
     */
    public function updateFolderProperties( $container, $path, $properties = array() )
    {
        $path = FileUtilities::fixFolderPath( $path );
        // does this folder exist?
        if ( !$this->folderExists( $container, $path ) )
        {
            throw new NotFoundException( "Folder '$path' does not exist." );
        }
        // update the file that holds folder properties
//            $properties = json_encode($properties);
//            $key = static::addContainerToName($container, $path);
//            $result = file_put_contents($key, $properties);
//            if (false === $result) {
//                throw new InternalServerErrorException('Failed to create folder properties.');
//            }
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $force If true, delete folder content as well,
     *                      otherwise return error when content present.
     *
     * @return void
     */
    public function deleteFolder( $container, $path, $force = false )
    {
        $_dir = static::addContainerToName( $container, $path );
        FileUtilities::deleteTree( $_dir, $force );
    }

    /**
     * @param string $container
     * @param array  $folders
     * @param string $root
     * @param bool   $force If true, delete folder content as well,
     *                      otherwise return error when content present.
     *
     * @throws BadRequestException
     * @return array
     */
    public function deleteFolders( $container, $folders, $root = '', $force = false )
    {
        $root = FileUtilities::fixFolderPath( $root );
        foreach ( $folders as $key => $folder )
        {
            try
            {
                // path is full path, name is relative to root, take either
                $_path = ArrayUtils::get( $folder, 'path' );
                if ( !empty( $_path ) )
                {
                    $_dir = static::asFullPath( $_path );
                    FileUtilities::deleteTree( $_dir, $force );
                }
                else
                {
                    $_name = ArrayUtils::get( $folder, 'name' );
                    if ( !empty( $_name ) )
                    {
                        $_path = $root . $_name;
                        $this->deleteFolder( $container, $_path, $force );
                    }
                    else
                    {
                        throw new BadRequestException( 'No path or name found for folder in delete request.' );
                    }
                }
            }
            catch ( \Exception $ex )
            {
                // error whole batch here?
                $folders[$key]['error'] = array( 'message' => $ex->getMessage(), 'code' => $ex->getCode() );
            }
        }

        return $folders;
    }

    /**
     * @param string $container
     * @param        $path
     *
     * @return bool
     */
    public function fileExists( $container, $path )
    {
        $key = static::addContainerToName( $container, $path );

        return is_file( $key ); // is_file() faster than file_exists()
    }

    /**
     * @param string $container
     * @param string $path
     * @param string $local_file
     * @param bool   $content_as_base
     *
     * @throws \Exception
     * @throws NotFoundException
     * @return string
     */
    public function getFileContent( $container, $path, $local_file = '', $content_as_base = true )
    {
        $_file = static::addContainerToName( $container, $path );
        if ( !is_file( $_file ) )
        {
            throw new NotFoundException( "File '$path' does not exist in storage." );
        }
        $_data = file_get_contents( $_file );
        if ( false === $_data )
        {
            throw new InternalServerErrorException( 'Failed to retrieve file content.' );
        }
        if ( !empty( $local_file ) )
        {
            // write to local or temp file
            $_result = file_put_contents( $local_file, $_data );
            if ( false === $_result )
            {
                throw new InternalServerErrorException( 'Failed to put file content as local file.' );
            }

            return '';
        }
        else
        {
            // get content as raw or encoded as base64 for transport
            if ( $content_as_base )
            {
                $_data = base64_encode( $_data );
            }

            return $_data;
        }
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $include_content
     * @param bool   $content_as_base
     *
     * @throws NotFoundException
     * @throws \Exception
     * @return array
     */
    public function getFileProperties( $container, $path, $include_content = false, $content_as_base = true )
    {
        if ( !$this->fileExists( $container, $path ) )
        {
            throw new NotFoundException( "File '$path' does not exist in storage." );
        }
        $_file = static::addContainerToName( $container, $path );
        $_shortName = FileUtilities::getNameFromPath( $path );
        $_ext = FileUtilities::getFileExtension( $_file );
        $_temp = stat( $_file );
        $_data = array(
            'path'           => $container . '/' . $path,
            'name'           => $_shortName,
            'content_type'   => FileUtilities::determineContentType( $_ext, '', $_file ),
            'last_modified'  => gmdate( 'D, d M Y H:i:s \G\M\T', ArrayUtils::get( $_temp, 'mtime', 0 ) ),
            'content_length' => ArrayUtils::get( $_temp, 'size', 0 )
        );
        if ( $include_content )
        {
            $_contents = file_get_contents( $_file );
            if ( false === $_contents )
            {
                throw new InternalServerErrorException( 'Failed to retrieve file properties.' );
            }
            if ( $content_as_base )
            {
                $_contents = base64_encode( $_contents );
            }
            $_data['content'] = $_contents;
        }

        return $_data;
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $download
     *
     * @return void
     */
    public function streamFile( $container, $path, $download = false )
    {
        $_file = static::addContainerToName( $container, $path );

        if ( !is_file( $_file ) )
        {
            throw new NotFoundException( "The specified file '" . $path . "' was not found in storage." );
        }

        FileUtilities::sendFile( $_file, $download );
    }

    /**
     * @param string $container
     * @param string $path
     * @param array  $properties
     *
     * @return void
     */
    public function updateFileProperties( $container, $path, $properties = array() )
    {
    }

    /**
     * @param string  $container
     * @param string  $path
     * @param string  $content
     * @param boolean $content_is_base
     * @param bool    $check_exist
     *
     * @throws NotFoundException
     * @throws \Exception
     * @return void
     */
    public function writeFile( $container, $path, $content, $content_is_base = false, $check_exist = false )
    {
        // does this file already exist?
        if ( $this->fileExists( $container, $path ) )
        {
            if ( ( $check_exist ) )
            {
                throw new InternalServerErrorException( "File '$path' already exists." );
            }
        }
        // does this folder's parent exist?
        $_parent = FileUtilities::getParentFolder( $path );
        if ( !empty( $_parent ) && ( !$this->folderExists( $container, $_parent ) ) )
        {
            throw new NotFoundException( "Folder '$_parent' does not exist." );
        }

        // create the file
        $this->checkContainerForWrite( $container ); // need to be able to write to storage
        if ( $content_is_base )
        {
            $content = base64_decode( $content );
        }
        $_file = static::addContainerToName( $container, $path );
        $_result = file_put_contents( $_file, $content );
        if ( false === $_result )
        {
            throw new InternalServerErrorException( 'Failed to create file.' );
        }
    }

    /**
     * @param string $container
     * @param string $path
     * @param string $local_path
     * @param bool   $check_exist
     *
     * @throws \Exception
     * @throws NotFoundException
     * @throws BadRequestException
     * @return void
     */
    public function moveFile( $container, $path, $local_path, $check_exist = true )
    {
        // does local file exist?
        if ( !file_exists( $local_path ) )
        {
            throw new NotFoundException( "File '$local_path' does not exist." );
        }
        // does this file already exist?
        if ( $this->fileExists( $container, $path ) )
        {
            if ( ( $check_exist ) )
            {
                throw new BadRequestException( "File '$path' already exists." );
            }
        }
        // does this file's parent folder exist?
        $_parent = FileUtilities::getParentFolder( $path );
        if ( !empty( $_parent ) && ( !$this->folderExists( $container, $_parent ) ) )
        {
            throw new NotFoundException( "Folder '$_parent' does not exist." );
        }

        // create the file
        $this->checkContainerForWrite( $container ); // need to be able to write to storage
        $_file = static::addContainerToName( $container, $path );
        if ( !rename( $local_path, $_file ) )
        {
            throw new InternalServerErrorException( "Failed to move file '$path'" );
        }
    }

    /**
     * @param string $container
     * @param string $dest_path
     * @param string $src_container
     * @param string $src_path
     * @param bool   $check_exist
     *
     * @throws \Exception
     * @throws NotFoundException
     * @throws BadRequestException
     * @return void
     */
    public function copyFile( $container, $dest_path, $src_container, $src_path, $check_exist = false )
    {
        // does this file already exist?
        if ( !$this->fileExists( $src_container, $src_path ) )
        {
            throw new NotFoundException( "File '$src_path' does not exist." );
        }
        if ( $this->fileExists( $container, $dest_path ) )
        {
            if ( ( $check_exist ) )
            {
                throw new BadRequestException( "File '$dest_path' already exists." );
            }
        }
        // does this file's parent folder exist?
        $_parent = FileUtilities::getParentFolder( $dest_path );
        if ( !empty( $_parent ) && ( !$this->folderExists( $container, $_parent ) ) )
        {
            throw new NotFoundException( "Folder '$_parent' does not exist." );
        }

        // create the file
        $this->checkContainerForWrite( $container ); // need to be able to write to storage
        $_file = static::addContainerToName( $src_container, $dest_path );
        $_srcFile = static::addContainerToName( $container, $src_path );
        $_result = copy( $_srcFile, $_file );
        if ( !$_result )
        {
            throw new InternalServerErrorException( 'Failed to copy file.' );
        }
    }

    /**
     * @param string $container
     * @param string $path
     *
     * @throws \Exception
     * @throws BadRequestException
     * @return void
     */
    public function deleteFile( $container, $path )
    {
        $_file = static::addContainerToName( $container, $path );
        if ( !is_file( $_file ) )
        {
            throw new BadRequestException( "'$_file' is not a valid filename." );
        }
        if ( !unlink( $_file ) )
        {
            throw new InternalServerErrorException( 'Failed to delete file.' );
        }
    }

    /**
     * @param string $container
     * @param array  $files
     * @param string $root
     *
     * @throws BadRequestException
     * @return array
     */
    public function deleteFiles( $container, $files, $root = '' )
    {
        $root = FileUtilities::fixFolderPath( $root );
        foreach ( $files as $_key => $_fileInfo )
        {
            try
            {
                // path is full path, name is relative to root, take either
                $_path = ArrayUtils::get( $_fileInfo, 'path' );
                if ( !empty( $_path ) )
                {
                    $_file = static::asFullPath( $_path, true );
                    if ( !is_file( $_file ) )
                    {
                        throw new BadRequestException( "'$_path' is not a valid file." );
                    }
                    if ( !unlink( $_file ) )
                    {
                        throw new InternalServerErrorException( "Failed to delete file '$_path'." );
                    }
                }
                else
                {
                    $_name = ArrayUtils::get( $_fileInfo, 'name' );
                    if ( !empty( $_name ) )
                    {
                        $_path = $root . $_name;
                        $this->deleteFile( $container, $_path );
                    }
                    else
                    {
                        throw new BadRequestException( 'No path or name found for file in delete request.' );
                    }
                }
            }
            catch ( \Exception $ex )
            {
                // error whole batch here?
                $files[$_key]['error'] = array( 'message' => $ex->getMessage(), 'code' => $ex->getCode() );
            }
        }

        return $files;
    }

    /**
     * @param string      $container
     * @param string      $path
     * @param \ZipArchive $zip
     * @param string      $zipFileName
     * @param bool        $overwrite
     *
     * @throws \Exception
     * @throws BadRequestException
     * @return string Zip File Name created/updated
     */
    public function getFolderAsZip( $container, $path, $zip = null, $zipFileName = '', $overwrite = false )
    {
        $_root = static::addContainerToName( $container, '' );
        if ( !is_dir( $_root ) )
        {
            throw new BadRequestException( "Can not find directory '$_root'." );
        }
        $_needClose = false;
        if ( !isset( $zip ) )
        {
            $_needClose = true;
            $zip = new \ZipArchive();
            if ( empty( $zipFileName ) )
            {
                $_temp = basename( $path );
                if ( empty( $_temp ) )
                {
                    $_temp = $container;
                }
                $_tempDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
                $zipFileName = $_tempDir . $_temp . '.zip';
            }
            if ( true !== $zip->open( $zipFileName, ( $overwrite ? \ZipArchive::OVERWRITE : \ZipArchive::CREATE ) ) )
            {
                throw new InternalServerErrorException( "Can not create zip file for directory '$path'." );
            }
        }
        FileUtilities::addTreeToZip( $zip, $_root, rtrim( $path, '/' ) );
        if ( $_needClose )
        {
            $zip->close();
        }

        return $zipFileName;
    }

    /**
     * @param string      $container
     * @param string      $path
     * @param \ZipArchive $zip
     * @param bool        $clean
     * @param string      $drop_path
     *
     * @throws \Exception
     * @return array
     */
    public function extractZipFile( $container, $path, $zip, $clean = false, $drop_path = '' )
    {
        if ( $clean )
        {
            try
            {
                // clear out anything in this directory
                $_dir = static::addContainerToName( $container, $path );
                FileUtilities::deleteTree( $_dir, true, false );
            }
            catch ( \Exception $ex )
            {
                throw new InternalServerErrorException( "Could not clean out existing directory $path.\n{$ex->getMessage()}" );
            }
        }
        for ( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $_name = $zip->getNameIndex( $i );
            if ( empty( $_name ) )
            {
                continue;
            }
            if ( !empty( $drop_path ) )
            {
                $_name = str_ireplace( $drop_path, '', $_name );
            }
            $fullPathName = $path . $_name;
            if ( '/' === substr( $fullPathName, -1 ) )
            {
                $this->createFolder( $container, $fullPathName, true, array(), false );
            }
            else
            {
                $parent = FileUtilities::getParentFolder( $fullPathName );
                if ( !empty( $parent ) )
                {
                    $this->createFolder( $container, $parent, true, array(), false );
                }
                $content = $zip->getFromIndex( $i );
                $this->writeFile( $container, $fullPathName, $content );
            }
        }

        return array( 'folder' => array( 'name' => rtrim( $path, DIRECTORY_SEPARATOR ), 'path' => $container . DIRECTORY_SEPARATOR . $path ) );
    }

    /**
     * @param      $name
     * @param bool $includesFiles
     *
     * @return string
     */
    private static function asFullPath( $name, $includesFiles = false )
    {
        $appendage = ( $name ? '/' . ltrim( $name, '/' ) : null );

        return static::$root . $appendage;

        //return Platform::getStoragePath( $name, true, $includesFiles );
    }

    /**
     * @param      $name
     * @param bool $includesFiles
     *
     * @return string
     */
    private static function asLocalPath( $name, $includesFiles = true )
    {
        return basename( static::asFullPath( $name, $includesFiles ) );
    }

    /**
     * @param $container
     * @param $name
     *
     * @return string
     */
    private static function addContainerToName( $container, $name )
    {
        if ( !empty( $container ) )
        {
            $container = FileUtilities::fixFolderPath( $container );
        }

        return static::asFullPath( $container . $name, true );
    }

    /**
     * @param $container
     * @param $name
     *
     * @return string
     */
    private static function removeContainerFromName( $container, $name )
    {
        $name = static::asLocalPath( $name );

        if ( empty( $container ) )
        {
            return $name;
        }
        $container = FileUtilities::fixFolderPath( $container );

        return substr( $name, strlen( $container ) + 1 );
    }

    /**
     * List folders and files
     *
     * @param  string $root      root path name
     * @param  string $prefix    Optional. search only for folders and files by specified prefix.
     * @param  string $delimiter Optional. Delimiter, i.e. '/', for specifying folder hierarchy
     *
     * @return array
     * @throws \Exception
     */
    public static function listTree( $root, $prefix = '', $delimiter = '' )
    {
        $dir = $root . ( ( !empty( $prefix ) ) ? $prefix : '' );
        $out = array();
        if ( is_dir( $dir ) )
        {
            $files = array_diff( scandir( $dir ), array( '.', '..' ) );
            foreach ( $files as $file )
            {
                $key = $dir . $file;
                $local = ( ( !empty( $prefix ) ) ? $prefix : '' ) . $file;
                // get file meta
                if ( is_dir( $key ) )
                {
                    $stat = stat( $key );
                    $out[] = array(
                        'path'          => str_replace( DIRECTORY_SEPARATOR, '/', $local ) . '/',
                        'last_modified' => gmdate( 'D, d M Y H:i:s \G\M\T', ArrayUtils::get( $stat, 'mtime', 0 ) )
                    );
                    if ( empty( $delimiter ) )
                    {
                        $out = array_merge( $out, static::listTree( $root, $local . DIRECTORY_SEPARATOR ) );
                    }
                }
                elseif ( is_file( $key ) )
                {
                    $stat = stat( $key );
                    $ext = FileUtilities::getFileExtension( $key );
                    $out[] = array(
                        'path'           => str_replace( DIRECTORY_SEPARATOR, '/', $local ),
                        'content_type'   => FileUtilities::determineContentType( $ext, '', $key ),
                        'last_modified'  => gmdate( 'D, d M Y H:i:s \G\M\T', ArrayUtils::get( $stat, 'mtime', 0 ) ),
                        'content_length' => ArrayUtils::get( $stat, 'size', 0 )
                    );
                }
                else
                {
                    error_log( $key );
                }
            }
        }
        else
        {
            throw new NotFoundException( "Folder '$prefix' does not exist in storage." );
        }

        return $out;
    }
}