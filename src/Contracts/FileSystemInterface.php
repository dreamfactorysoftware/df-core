<?php

namespace DreamFactory\Core\Contracts;

interface FileSystemInterface
{
    const TIMESTAMP_FORMAT = 'D, d M Y H:i:s \G\M\T';

    /**
     * List all containers, just names if noted
     *
     * @param bool $include_properties If true, additional properties are retrieved
     *
     * @return array
     */
    public function listContainers($include_properties = false);

    /**
     * Check if a container exists
     *
     * @param string $container Container name
     *
     * @return boolean
     */
    public function containerExists($container);

    /**
     * Gets all properties of a particular container, if options are false,
     * otherwise include content from the container
     *
     * @param string $container Container name
     * @param bool   $include_files
     * @param bool   $include_folders
     * @param bool   $full_tree
     *
     * @return array
     */
    public function getContainer($container, $include_files = true, $include_folders = true, $full_tree = false);

    /**
     * Gets all properties of a particular container
     *
     * @param string $container Container name
     *
     * @return array
     */
    public function getContainerProperties($container);

    /**
     * Create a container using properties, where at least name is required
     *
     * @param array $container
     * @param bool  $check_exist If true, throws error if the container already exists
     *
     * @return array
     */
    public function createContainer($container, $check_exist = false);

    /**
     * Create multiple containers using array of properties, where at least name is required
     *
     * @param array $containers
     * @param bool  $check_exist If true, throws error if the container already exists
     *
     * @return array
     */
    public function createContainers($containers, $check_exist = false);

    /**
     * Update a container with some properties
     *
     * @param string $container
     * @param array  $properties
     *
     * @return void
     */
    public function updateContainerProperties($container, $properties = []);

    /**
     * Delete a container and all of its content
     *
     * @param string $container
     * @param bool   $force Force a delete if it is not empty
     *
     * @return void
     */
    public function deleteContainer($container, $force = false);

    /**
     * Delete multiple containers and all of their content
     *
     * @param array $containers
     * @param bool  $force Force a delete if it is not empty
     *
     * @return array
     */
    public function deleteContainers($containers, $force = false);

    /**
     * @param string $container
     * @param string $path
     *
     * @return bool
     */
    public function folderExists($container, $path);

    /**
     * Gets all resources with properties of a particular folder
     *
     * @param string $container
     * @param string $path
     * @param bool   $include_files
     * @param bool   $include_folders
     * @param bool   $full_tree
     *
     * @return array
     */
    public function getFolder($container, $path, $include_files = true, $include_folders = true, $full_tree = false);

    /**
     * Gets all properties of a particular folder
     *
     * @param string $container
     * @param string $path
     *
     * @return array
     */
    public function getFolderProperties($container, $path);

    /**
     * @param string $container
     * @param string $path
     * @param array  $properties
     *
     * @return void
     */
    public function createFolder($container, $path, $properties = []);

    /**
     * @param string       $container
     * @param string       $path
     * @param array|string $properties
     *
     * @return void
     */
    public function updateFolderProperties($container, $path, $properties = []);

    /**
     * @param string $container
     * @param string $dest_path
     * @param string $src_container
     * @param string $src_path
     * @param bool   $check_exist
     *
     * @return void
     */
    public function copyFolder($container, $dest_path, $src_container, $src_path, $check_exist = false);

    /**
     * @param string $container
     * @param array  $folders
     * @param string $root
     * @param bool   $force If true, delete folder content as well,
     *                      otherwise return error when content present.
     *
     * @return array
     */
    public function deleteFolders($container, $folders, $root = '', $force = false);

    /**
     * @param string $container
     * @param string $path Folder path relative to the service root directory
     * @param bool   $force
     * @param bool   $content_only
     *
     * @return void
     */
    public function deleteFolder($container, $path, $force = false, $content_only = false);

    /**
     * @param string $container
     * @param string $path
     *
     * @return bool
     */
    public function fileExists($container, $path);

    /**
     * @param string $container
     * @param string $path
     * @param string $local_file
     * @param bool   $content_as_base
     *
     * @return string
     */
    public function getFileContent($container, $path, $local_file = null, $content_as_base = true);

    /**
     * @param string $container
     * @param string $path
     * @param bool   $include_content
     * @param bool   $content_as_base
     *
     * @return array
     */
    public function getFileProperties($container, $path, $include_content = false, $content_as_base = true);

    /**
     * @param string $container
     * @param string $path
     * @param bool   $download
     *
     * @return void
     */
    public function streamFile($container, $path, $download = false);

    /**
     * @param string $container
     * @param string $path
     * @param array  $properties
     *
     * @return void
     */
    public function updateFileProperties($container, $path, $properties = []);

    /**
     * @param string  $container
     * @param string  $path
     * @param string  $content
     * @param boolean $content_is_base
     * @param bool    $check_exist
     *
     * @return void
     */
    public function writeFile($container, $path, $content, $content_is_base = true, $check_exist = false);

    /**
     * @param string $container
     * @param string $path
     * @param string $local_path
     * @param bool   $check_exist
     *
     * @return void
     */
    public function moveFile($container, $path, $local_path, $check_exist = false);

    /**
     * @param string $container
     * @param string $dest_path
     * @param string $sc_container
     * @param string $src_path
     * @param bool   $check_exist
     *
     * @return void
     */
    public function copyFile($container, $dest_path, $sc_container, $src_path, $check_exist = false);

    /**
     * @param string $container
     * @param string $path    File path relative to the service root directory
     * @param bool   $noCheck Set true to avoid checking file existence
     *
     * @return void
     */
    public function deleteFile($container, $path, $noCheck = false);

    /**
     * @param string $container
     * @param array  $files Array of file paths relative to root
     * @param string $root
     *
     * @return array
     */
    public function deleteFiles($container, $files, $root = null);

    /**
     * @param string      $container
     * @param string      $path
     * @param \ZipArchive $zip
     * @param bool        $clean
     * @param string      $drop_path
     *
     * @return array
     */
    public function extractZipFile($container, $path, $zip, $clean = false, $drop_path = null);

    /**
     * @param string           $container
     * @param string           $path
     * @param null|\ZipArchive $zip
     * @param string           $zipFileName
     * @param bool             $overwrite
     *
     * @return string Zip File Name created/updated
     */
    public function getFolderAsZip($container, $path, $zip = null, $zipFileName = null, $overwrite = false);
}