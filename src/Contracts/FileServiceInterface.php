<?php

namespace DreamFactory\Core\Contracts;

interface FileServiceInterface
{
    /**
     * @param $path
     * @return boolean
     */
    public function isPublicPath($path);

    /**
     * @param $path
     * @return boolean
     */
    public function folderExists($path);

    /**
     * Gets all resources with properties of a particular folder
     *
     * @param string $path
     * @param bool   $include_files
     * @param bool   $include_folders
     * @param bool   $full_tree
     *
     * @return array
     */
    public function getFolder($path, $include_files = true, $include_folders = true, $full_tree = false);

    /**
     * Gets all properties of a particular folder
     *
     * @param string $path
     *
     * @return array
     */
    public function getFolderProperties($path);

    /**
     * @param string $path
     * @param array  $properties
     *
     * @return void
     */
    public function createFolder($path, $properties = []);

    /**
     * @param string       $path
     * @param array|string $properties
     *
     * @return void
     */
    public function updateFolderProperties($path, $properties = []);

    /**
     * @param string $dest_path
     * @param string $src_container
     * @param string $src_path
     * @param bool   $check_exist
     *
     * @return void
     */
    public function copyFolder($dest_path, $src_container, $src_path, $check_exist = false);

    /**
     * @param array  $folders
     * @param string $root
     * @param bool   $force If true, delete folder content as well,
     *                      otherwise return error when content present.
     *
     * @return array
     */
    public function deleteFolders($folders, $root = '', $force = false);

    /**
     * @param string $path
     * @param bool   $force
     * @param bool   $content_only
     * @return void
     */
    public function deleteFolder($path, $force = false, $content_only = false);

    /**
     * @param string $path
     *
     * @return bool
     */
    public function fileExists($path);

    /**
     * @param string $path
     * @param string $local_file
     * @param bool   $content_as_base
     *
     * @return string
     */
    public function getFileContent($path, $local_file = null, $content_as_base = true);

    /**
     * @param string $path
     * @param bool   $include_content
     * @param bool   $content_as_base
     *
     * @return array
     */
    public function getFileProperties($path, $include_content = false, $content_as_base = true);

    /**
     * @param string $path
     * @param array  $properties
     *
     * @return void
     */
    public function updateFileProperties($path, $properties = []);

    /**
     * @param string  $path
     * @param string  $content
     * @param boolean $content_is_base
     * @param bool    $check_exist
     *
     * @return void
     */
    public function writeFile($path, $content, $content_is_base = true, $check_exist = false);

    /**
     * @param string $path
     * @param string $local_path
     * @param bool   $check_exist
     *
     * @return void
     */
    public function moveFile($path, $local_path, $check_exist = false);

    /**
     * @param string $dest_path
     * @param string $sc_container
     * @param string $src_path
     * @param bool   $check_exist
     *
     * @return void
     */
    public function copyFile($dest_path, $sc_container, $src_path, $check_exist = false);

    /**
     * @param string $path    File path relative to the service root directory
     * @param bool   $noCheck Set true to avoid checking file existence
     *
     * @return void
     */
    public function deleteFile($path, $noCheck = false);

    /**
     * @param array  $files Array of file paths relative to root
     * @param string $root
     *
     * @return array
     */
    public function deleteFiles($files, $root = null);

    /**
     * @param string $path
     * @param bool   $download
     */
    public function streamFile($path, $download = false);

    /**
     * @param string           $path
     * @param null|\ZipArchive $zip
     * @param string           $zipFileName
     * @param bool             $overwrite
     *
     * @return string Zip File Name created/updated
     */
    public function getFolderAsZip($path, $zip = null, $zipFileName = null, $overwrite = false);

    /**
     * @param            $path
     * @param            $zip
     * @param bool|false $clean
     * @param null       $drop_path
     *
     * @return array
     */
    public function extractZipFile($path, $zip, $clean = false, $drop_path = null);
}