<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Core\Contracts\FileSystemInterface;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

/**
 * Class LocalFileSystem
 *
 * @package DreamFactory\Core\Components
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

    public function __construct($root)
    {
        if (empty($root)) {
            throw new InternalServerErrorException("Invalid root supplied for local file system.");
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
    public function checkContainerForWrite($container)
    {
        $container = static::addContainerToName($container, '');
        if (!is_dir($container)) {
            if (!mkdir($container, 0777, true)) {
                throw new InternalServerErrorException('Failed to create container.');
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
    public function listContainers($include_properties = false)
    {
        $out = [];
        $root = FileUtilities::fixFolderPath(static::asFullPath('', false));
        $files = array_diff(scandir($root), ['.', '..', '.private']);
        foreach ($files as $file) {
            $dir = $root . $file;
            // get file meta
            if (is_dir($dir) || is_file($dir)) {
                $result = ['name' => $file, 'path' => $file];
                if ($include_properties) {
                    $temp = stat($dir);
                    $result['last_modified'] = gmdate(static::TIMESTAMP_FORMAT, ArrayUtils::get($temp, 'mtime', 0));
                }

                $out[] = $result;
            }
        }

        return $out;
    }

    /**
     * Check if a container exists
     *
     * @param  string $container Container name
     *
     * @return boolean
     */
    public function containerExists($container)
    {
        $dir = static::addContainerToName($container, '');

        return is_dir($dir);
    }

    /**
     * Gets content of a particular container
     *
     * @param string $container Container name
     * @param bool   $include_files
     * @param bool   $include_folders
     * @param bool   $full_tree
     *
     * @return array
     */
    public function getContainer($container, $include_files = true, $include_folders = true, $full_tree = false)
    {
        return $this->getFolder($container, '', $include_files, $include_folders, $full_tree);
    }

    /**
     * Gets all properties of a particular container
     *
     * @param string $container Container name
     *
     * @return array
     */
    public function getContainerProperties($container)
    {
        return $this->getFolderProperties($container, '');
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
    public function createContainer($properties = [], $check_exist = false)
    {
        $container = ArrayUtils::get($properties, 'name', ArrayUtils::get($properties, 'path'));
        if (empty($container)) {
            throw new BadRequestException('No name found for container in create request.');
        }
        // does this folder already exist?
        if ($this->folderExists($container, '')) {
            if ($check_exist) {
                throw new BadRequestException("Container '$container' already exists.");
            }
        } else {
            // create the container
            $dir = static::addContainerToName($container, '');

            if (!mkdir($dir, 0777, true)) {
                throw new InternalServerErrorException('Failed to create container.');
            }
        }

        return ['name' => $container, 'path' => $container];

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
    public function createContainers($containers = [], $check_exist = false)
    {
        if (empty($containers)) {
            return [];
        }

        $out = [];
        foreach ($containers as $key => $folder) {
            try {
                // path is full path, name is relative to root, take either
                $out[$key] = $this->createContainer($folder, $check_exist);
            } catch (\Exception $ex) {
                // error whole batch here?
                $out[$key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
            }
        }

        return $out;
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
    public function updateContainerProperties($container, $properties = [])
    {
        // does this folder exist?
        if (!$this->folderExists($container, '')) {
            throw new NotFoundException("Container '$container' does not exist.");
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
    public function deleteContainer($container, $force = false)
    {
        $dir = static::addContainerToName($container, '');
        if (!rmdir($dir)) {
            throw new InternalServerErrorException('Failed to delete container.');
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
    public function deleteContainers($containers, $force = false)
    {
        if (!empty($containers)) {
            if (!isset($containers[0])) {
                // single folder, make into array
                $containers = [$containers];
            }
            foreach ($containers as $key => $folder) {
                try {
                    // path is full path, name is relative to root, take either
                    $name = ArrayUtils::get($folder, 'name', trim(ArrayUtils::get($folder, 'path'), '/'));
                    if (!empty($name)) {
                        $this->deleteContainer($name, $force);
                    } else {
                        throw new BadRequestException('No name found for container in delete request.');
                    }
                } catch (\Exception $ex) {
                    // error whole batch here?
                    $containers[$key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
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
    public function folderExists($container, $path)
    {
        $path = FileUtilities::fixFolderPath($path);
        $dir = static::addContainerToName($container, $path);

        return is_dir($dir);
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $include_files
     * @param bool   $include_folders
     * @param bool   $full_tree
     *
     * @throws NotFoundException
     * @return array
     */
    public function getFolder($container, $path, $include_files = true, $include_folders = true, $full_tree = false)
    {
        $path = FileUtilities::fixFolderPath($path);
        $delimiter = ($full_tree) ? '' : DIRECTORY_SEPARATOR;
        $resources = [];
        $dirPath = FileUtilities::fixFolderPath(static::asFullPath(''));
        if (is_dir($dirPath)) {
            $localizer = $path;
            $results = static::listTree($dirPath, $path, $delimiter);
            foreach ($results as $data) {
                $fullPathName = $data['path'];
                $data['name'] = rtrim(substr($fullPathName, strlen($localizer)), '/');
                if ('/' == substr($fullPathName, -1, 1)) {
                    // folders
                    if ($include_folders) {
                        $data['type'] = 'folder';
                        $resources[] = $data;
                    }
                } else {
                    // files
                    if ($include_files) {
                        $data['type'] = 'file';
                        $resources[] = $data;
                    }
                }
            }
        } else {
            if (!empty($path)) {
                throw new NotFoundException("Folder '$path' does not exist in storage.");
            }
            // container root doesn't really exist until first write creates it
        }

        return $resources;
    }

    /**
     * @param string $container
     * @param string $path
     *
     * @throws NotFoundException
     * @return array
     */
    public function getFolderProperties($container, $path)
    {
        $path = FileUtilities::fixFolderPath($path);
        $out = ['name' => basename($path), 'path' => $path];
        $dirPath = static::addContainerToName($container, $path);
        $temp = stat($dirPath);
        $out['last_modified'] = gmdate(static::TIMESTAMP_FORMAT, ArrayUtils::get($temp, 'mtime', 0));

        return $out;
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
    public function createFolder($container, $path, $is_public = true, $properties = [], $check_exist = true)
    {
        if (empty($path)) {
            throw new BadRequestException("Invalid empty path.");
        }
        $path = FileUtilities::fixFolderPath($path);

        // does this folder already exist?
        if ($this->folderExists($container, $path)) {
            if ($check_exist) {
                throw new BadRequestException("Folder '$path' already exists.");
            }

            return;
        }

        // create the folder
        $this->checkContainerForWrite($container); // need to be able to write to storage
        $dir = static::addContainerToName($container, $path);

        if (false === @mkdir($dir, 0777, true)) {
            \Log::error('Unable to create directory: ' . $dir);
            throw new InternalServerErrorException('Failed to create folder: ' . $path);
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
    public function copyFolder($container, $dest_path, $src_container, $src_path, $check_exist = false)
    {
        // does this file already exist?
        if (!$this->folderExists($src_container, $src_path)) {
            throw new NotFoundException("Folder '$src_path' does not exist.");
        }
        if ($this->folderExists($container, $dest_path)) {
            if (($check_exist)) {
                throw new BadRequestException("Folder '$dest_path' already exists.");
            }
        }
        // does this file's parent folder exist?
        $parent = FileUtilities::getParentFolder($dest_path);
        if (!empty($parent) && (!$this->folderExists($container, $parent))) {
            throw new NotFoundException("Folder '$parent' does not exist.");
        }
        // create the folder
        $this->checkContainerForWrite($container); // need to be able to write to storage
        FileUtilities::copyTree(
            static::addContainerToName($src_container, $src_path),
            static::addContainerToName($container, $dest_path)
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
    public function updateFolderProperties($container, $path, $properties = [])
    {
        $path = FileUtilities::fixFolderPath($path);
        // does this folder exist?
        if (!$this->folderExists($container, $path)) {
            throw new NotFoundException("Folder '$path' does not exist.");
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
    public function deleteFolder($container, $path, $force = false)
    {
        $dir = static::addContainerToName($container, $path);
        FileUtilities::deleteTree($dir, $force);
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
    public function deleteFolders($container, $folders, $root = '', $force = false)
    {
        $root = FileUtilities::fixFolderPath($root);
        foreach ($folders as $key => $folder) {
            try {
                // path is full path, name is relative to root, take either
                $path = ArrayUtils::get($folder, 'path');
                if (!empty($path)) {
                    $dir = static::asFullPath($path);
                    FileUtilities::deleteTree($dir, $force);
                } else {
                    $name = ArrayUtils::get($folder, 'name');
                    if (!empty($name)) {
                        $path = $root . $name;
                        $this->deleteFolder($container, $path, $force);
                    } else {
                        throw new BadRequestException('No path or name found for folder in delete request.');
                    }
                }
            } catch (\Exception $ex) {
                // error whole batch here?
                $folders[$key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
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
    public function fileExists($container, $path)
    {
        $key = static::addContainerToName($container, $path);

        return is_file($key); // is_file() faster than file_exists()
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
    public function getFileContent($container, $path, $local_file = '', $content_as_base = true)
    {
        $file = static::addContainerToName($container, $path);
        if (!is_file($file)) {
            throw new NotFoundException("File '$path' does not exist in storage.");
        }
        $data = file_get_contents($file);
        if (false === $data) {
            throw new InternalServerErrorException('Failed to retrieve file content.');
        }
        if (!empty($local_file)) {
            // write to local or temp file
            $result = file_put_contents($local_file, $data);
            if (false === $result) {
                throw new InternalServerErrorException('Failed to put file content as local file.');
            }

            return '';
        } else {
            // get content as raw or encoded as base64 for transport
            if ($content_as_base) {
                $data = base64_encode($data);
            }

            return $data;
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
    public function getFileProperties($container, $path, $include_content = false, $content_as_base = true)
    {
        if (!$this->fileExists($container, $path)) {
            throw new NotFoundException("File '$path' does not exist in storage.");
        }
        $file = static::addContainerToName($container, $path);
        $shortName = FileUtilities::getNameFromPath($path);
        $ext = FileUtilities::getFileExtension($file);
        $temp = stat($file);
        $data = [
            'path'           => $path,
            'name'           => $shortName,
            'content_type'   => FileUtilities::determineContentType($ext, '', $file),
            'last_modified'  => gmdate('D, d M Y H:i:s \G\M\T', ArrayUtils::get($temp, 'mtime', 0)),
            'content_length' => ArrayUtils::get($temp, 'size', 0)
        ];
        if ($include_content) {
            $contents = file_get_contents($file);
            if (false === $contents) {
                throw new InternalServerErrorException('Failed to retrieve file properties.');
            }
            if ($content_as_base) {
                $contents = base64_encode($contents);
            }
            $data['content'] = $contents;
        }

        return $data;
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $download
     *
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    public function streamFile($container, $path, $download = false)
    {
        $file = static::addContainerToName($container, $path);

        if (!is_file($file)) {
            throw new NotFoundException("The specified file '" . $path . "' was not found in storage.");
        }

        FileUtilities::sendFile($file, $download);
    }

    /**
     * @param string $container
     * @param string $path
     * @param array  $properties
     *
     * @return void
     */
    public function updateFileProperties($container, $path, $properties = [])
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
    public function writeFile($container, $path, $content, $content_is_base = false, $check_exist = false)
    {
        // does this file already exist?
        if ($this->fileExists($container, $path)) {
            if (($check_exist)) {
                throw new InternalServerErrorException("File '$path' already exists.");
            }
        }
        // does this folder's parent exist?
        $parent = FileUtilities::getParentFolder($path);
        if (!empty($parent) && (!$this->folderExists($container, $parent))) {
            throw new NotFoundException("Folder '$parent' does not exist.");
        }

        // create the file
        $this->checkContainerForWrite($container); // need to be able to write to storage
        if ($content_is_base) {
            $content = base64_decode($content);
        }
        $file = static::addContainerToName($container, $path);
        $result = file_put_contents($file, $content);
        if (false === $result) {
            throw new InternalServerErrorException('Failed to create file.');
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
    public function moveFile($container, $path, $local_path, $check_exist = true)
    {
        // does local file exist?
        if (!file_exists($local_path)) {
            throw new NotFoundException("File '$local_path' does not exist.");
        }
        // does this file already exist?
        if ($this->fileExists($container, $path)) {
            if (($check_exist)) {
                throw new BadRequestException("File '$path' already exists.");
            }
        }
        // does this file's parent folder exist?
        $parent = FileUtilities::getParentFolder($path);
        if (!empty($parent) && (!$this->folderExists($container, $parent))) {
            throw new NotFoundException("Folder '$parent' does not exist.");
        }

        // create the file
        $this->checkContainerForWrite($container); // need to be able to write to storage
        $file = static::addContainerToName($container, $path);
        if (!rename($local_path, $file)) {
            throw new InternalServerErrorException("Failed to move file '$path'");
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
    public function copyFile($container, $dest_path, $src_container, $src_path, $check_exist = false)
    {
        // does this file already exist?
        if (!$this->fileExists($src_container, $src_path)) {
            throw new NotFoundException("File '$src_path' does not exist.");
        }
        if ($this->fileExists($container, $dest_path)) {
            if (($check_exist)) {
                throw new BadRequestException("File '$dest_path' already exists.");
            }
        }
        // does this file's parent folder exist?
        $parent = FileUtilities::getParentFolder($dest_path);
        if (!empty($parent) && (!$this->folderExists($container, $parent))) {
            throw new NotFoundException("Folder '$parent' does not exist.");
        }

        // create the file
        $this->checkContainerForWrite($container); // need to be able to write to storage
        $file = static::addContainerToName($src_container, $dest_path);
        $srcFile = static::addContainerToName($container, $src_path);
        $result = copy($srcFile, $file);
        if (!$result) {
            throw new InternalServerErrorException('Failed to copy file.');
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
    public function deleteFile($container, $path)
    {
        $file = static::addContainerToName($container, $path);
        if (!is_file($file)) {
            throw new BadRequestException("'$file' is not a valid filename.");
        }
        if (!unlink($file)) {
            throw new InternalServerErrorException('Failed to delete file.');
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
    public function deleteFiles($container, $files, $root = '')
    {
        $root = FileUtilities::fixFolderPath($root);
        foreach ($files as $key => $fileInfo) {
            try {
                // path is full path, name is relative to root, take either
                $path = ArrayUtils::get($fileInfo, 'path');
                if (!empty($path)) {
                    $file = static::asFullPath($path, true);
                    if (!is_file($file)) {
                        throw new BadRequestException("'$path' is not a valid file.");
                    }
                    if (!unlink($file)) {
                        throw new InternalServerErrorException("Failed to delete file '$path'.");
                    }
                } else {
                    $name = ArrayUtils::get($fileInfo, 'name');
                    if (!empty($name)) {
                        $path = $root . $name;
                        $this->deleteFile($container, $path);
                    } else {
                        throw new BadRequestException('No path or name found for file in delete request.');
                    }
                }
            } catch (\Exception $ex) {
                // error whole batch here?
                $files[$key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
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
    public function getFolderAsZip($container, $path, $zip = null, $zipFileName = '', $overwrite = false)
    {
        $root = static::addContainerToName($container, '');
        if (!is_dir($root)) {
            throw new BadRequestException("Can not find directory '$root'.");
        }
        $needClose = false;
        if (!isset($zip)) {
            $needClose = true;
            $zip = new \ZipArchive();
            if (empty($zipFileName)) {
                $temp = basename($path);
                if (empty($temp)) {
                    $temp = $container;
                }
                $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $zipFileName = $tempDir . $temp . '.zip';
            }
            if (true !== $zip->open($zipFileName, ($overwrite ? \ZipArchive::OVERWRITE : \ZipArchive::CREATE))) {
                throw new InternalServerErrorException("Can not create zip file for directory '$path'.");
            }
        }
        FileUtilities::addTreeToZip($zip, $root, rtrim($path, '/'));
        if ($needClose) {
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
    public function extractZipFile($container, $path, $zip, $clean = false, $drop_path = '')
    {
        if ($clean) {
            try {
                // clear out anything in this directory
                $dir = static::addContainerToName($container, $path);
                FileUtilities::deleteTree($dir, true, false);
            } catch (\Exception $ex) {
                throw new InternalServerErrorException("Could not clean out existing directory $path.\n{$ex->getMessage()}");
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (empty($name)) {
                continue;
            }
            if (!empty($drop_path)) {
                $name = str_ireplace($drop_path, '', $name);
            }
            $fullPathName = $path . $name;
            if ('/' === substr($fullPathName, -1)) {
                $this->createFolder($container, $fullPathName, true, [], false);
            } else {
                $parent = FileUtilities::getParentFolder($fullPathName);
                if (!empty($parent)) {
                    $this->createFolder($container, $parent, true, [], false);
                }
                $content = $zip->getFromIndex($i);
                $this->writeFile($container, $fullPathName, $content);
            }
        }

        return [
            'name' => rtrim($path, DIRECTORY_SEPARATOR),
            'path' => $path,
            'type' => 'file'
        ];
    }

    /**
     * @param      $name
     * @param bool $includesFiles
     *
     * @return string
     */
    private static function asFullPath($name, $includesFiles = false)
    {
        $appendage = ($name ? '/' . ltrim($name, '/') : null);

        return static::$root . $appendage;
        //return Platform::getStoragePath( $name, true, $includesFiles );
    }

    /**
     * @param      $name
     * @param bool $includesFiles
     *
     * @return string
     */
    private static function asLocalPath($name, $includesFiles = true)
    {
        return basename(static::asFullPath($name, $includesFiles));
    }

    /**
     * @param $container
     * @param $name
     *
     * @return string
     */
    private static function addContainerToName($container, $name)
    {
        if (!empty($container)) {
            $container = FileUtilities::fixFolderPath($container);
        }

        return static::asFullPath($container . $name, true);
    }

    /**
     * @param $container
     * @param $name
     *
     * @return string
     */
    private static function removeContainerFromName($container, $name)
    {
        $name = static::asLocalPath($name);

        if (empty($container)) {
            return $name;
        }
        $container = FileUtilities::fixFolderPath($container);

        return substr($name, strlen($container) + 1);
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
    public static function listTree($root, $prefix = '', $delimiter = '')
    {
        $dir = $root . ((!empty($prefix)) ? $prefix : '');
        $out = [];
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $key = $dir . $file;
                $local = ((!empty($prefix)) ? $prefix : '') . $file;
                // get file meta
                if (is_dir($key)) {
                    $stat = stat($key);
                    $out[] = [
                        'path'          => str_replace(DIRECTORY_SEPARATOR, '/', $local) . '/',
                        'last_modified' => gmdate('D, d M Y H:i:s \G\M\T', ArrayUtils::get($stat, 'mtime', 0))
                    ];
                    if (empty($delimiter)) {
                        $out = array_merge($out, static::listTree($root, $local . DIRECTORY_SEPARATOR));
                    }
                } elseif (is_file($key)) {
                    $stat = stat($key);
                    $ext = FileUtilities::getFileExtension($key);
                    $out[] = [
                        'path'           => str_replace(DIRECTORY_SEPARATOR, '/', $local),
                        'content_type'   => FileUtilities::determineContentType($ext, '', $key),
                        'last_modified'  => gmdate('D, d M Y H:i:s \G\M\T', ArrayUtils::get($stat, 'mtime', 0)),
                        'content_length' => ArrayUtils::get($stat, 'size', 0)
                    ];
                } else {
                    error_log($key);
                }
            }
        } else {
            throw new NotFoundException("Folder '$prefix' does not exist in storage.");
        }

        return $out;
    }
}