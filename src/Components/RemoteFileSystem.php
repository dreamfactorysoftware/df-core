<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\FileSystemInterface;
use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\DfException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

/**
 * Class RemoteFileSystem (a copy of dsp's RemoteFileSvc class)
 *
 * @package DreamFactory\Core\Components
 */
abstract class RemoteFileSystem implements FileSystemInterface
{
    protected $container = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Creates the container for this file management if it does not already exist
     *
     * @param string $container
     *
     * @throws \Exception
     */
    public function checkContainerForWrite($container)
    {
        if (!$this->containerExists($container)) {
            $this->createContainer(['name' => $container]);
        }
    }

    /**
     * Create multiple containers using array of properties, where at least name is required
     *
     * @param array $containers
     * @param bool  $check_exist If true, throws error if the container already exists
     *
     * @return array
     */
    public function createContainers($containers, $check_exist = false)
    {
        $out = [];

        if (!empty($containers)) {
            if (!isset($containers[0])) {
                // single folder, make into array
                $containers = [$containers];
            }
            foreach ($containers as $key => $folder) {
                try {
                    // path is full path, name is relative to root, take either
                    $out[$key] = $this->createContainer($folder, $check_exist);
                } catch (\Exception $ex) {
                    // error whole batch here?
                    $out[$key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
                }
            }
        }

        return $out;
    }

    /**
     * Delete multiple containers and all of their content
     *
     * @param array $containers
     * @param bool  $force Force a delete if it is not empty
     *
     * @throws DfException
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
                        throw new DfException('No name found for container in delete request.');
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
     * @param string $container
     * @param        $path
     *
     * @return bool
     * @throws \Exception
     */
    public function folderExists($container, $path)
    {
        $path = FileUtilities::fixFolderPath($path);
        if ($this->containerExists($container)) {
            if ($this->blobExists($container, $path)) {
                return true;
            }
        }

        return false;
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
        $delimiter = ($full_tree) ? '' : '/';
        $resources = [];
        if ($this->containerExists($container)) {
            if (!empty($path)) {
                if (!$this->blobExists($container, $path)) {
                    // blob may not exist for "fake" folders, i.e. S3 prefixes
//					throw new NotFoundException( "Folder '$path' does not exist in storage." );
                }
            }
            $results = $this->listBlobs($container, $path, $delimiter);
            foreach ($results as $data) {
                $fullPathName = ArrayUtils::get($data, 'name');
                $data['path'] = $fullPathName;
                $data['name'] = rtrim(substr($fullPathName, strlen($path)), '/');
                if ('/' == substr($fullPathName, -1)) {
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
        $shortName = FileUtilities::getNameFromPath($path);
        $out = ['name' => $shortName, 'path' => $path];
        if ($this->containerExists($container) && $this->blobExists($container, $path)) {
            $properties = $this->getBlobProperties($container, $path);
            $out = array_merge($properties, $out);
        }

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

        // does this folder already exist?
        $path = FileUtilities::fixFolderPath($path);
        if ($this->folderExists($container, $path)) {
            if ($check_exist) {
                throw new BadRequestException("Folder '$path' already exists.");
            }

            return;
        }
        // does this folder's parent exist?
        $parent = FileUtilities::getParentFolder($path);
        if (!empty($parent) && (!$this->folderExists($container, $parent))) {
            if ($check_exist) {
                throw new NotFoundException("Folder '$parent' does not exist.");
            }
            $this->createFolder($container, $parent, $is_public, $properties, false);
        }

        // create the folder
        $this->checkContainerForWrite($container); // need to be able to write to storage
        $properties = (empty($properties)) ? '' : json_encode($properties);
        $this->putBlobData($container, $path, $properties);
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
    public function copyFolder($container, $dest_path, $src_container, $src_path, $check_exist = false)
    {
        // does this file already exist?
        if (!$this->folderExists($container, $src_path)) {
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
        $this->copyBlob($container, $dest_path, $src_container, $src_path);
        // now copy content of folder...
        $blobs = $this->listBlobs($src_container, $src_path);
        if (!empty($blobs)) {
            foreach ($blobs as $blob) {
                $srcName = ArrayUtils::get($blob, 'name');
                if ((0 !== strcasecmp($src_path, $srcName))) {
                    // not self properties blob
                    $name = FileUtilities::getNameFromPath($srcName);
                    $fullPathName = $dest_path . $name;
                    $this->copyBlob($container, $fullPathName, $src_container, $srcName);
                }
            }
        }
    }

    /**
     * @param string $container
     * @param string $path
     * @param array  $properties
     *
     * @throws NotFoundException
     * @throws \Exception
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
        $properties = json_encode($properties);
        $this->putBlobData($container, $path, $properties);
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $force If true, delete folder content as well,
     *                      otherwise return error when content present.
     *
     * @return void
     * @throws \Exception
     */
    public function deleteFolder($container, $path, $force = false)
    {
        $path = rtrim($path, '/') . '/';
        $blobs = $this->listBlobs($container, $path);
        if (!empty($blobs)) {
            if ((1 === count($blobs)) && (0 === strcasecmp($path, $blobs[0]['name']))) {
                // only self properties blob
            } else {
                if (!$force) {
                    throw new BadRequestException("Folder '$path' contains other files or folders.");
                }
                foreach ($blobs as $blob) {
                    $name = ArrayUtils::get($blob, 'name');
                    $this->deleteBlob($container, $name);
                }
            }
        }
        $this->deleteBlob($container, $path);
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
                $name = ArrayUtils::get($folder, 'name');
                if (!empty($path)) {
                    $path = static::removeContainerFromPath($container, $path);
                } elseif (!empty($name)) {
                    $path = $root . $folder['name'];
                } else {
                    throw new BadRequestException('No path or name found for folder in delete request.');
                }
                if (!empty($path)) {
                    $this->deleteFolder($container, $path, $force);
                } else {
                    throw new BadRequestException('No path or name found for folder in delete request.');
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
     * @throws \Exception
     * @return bool
     */
    public function fileExists($container, $path)
    {
        if ($this->containerExists($container)) {
            if ($this->blobExists($container, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $container
     * @param string $path
     * @param string $local_file
     * @param bool   $content_as_base
     *
     * @throws NotFoundException
     * @throws \Exception
     * @return string
     */
    public function getFileContent($container, $path, $local_file = '', $content_as_base = true)
    {
        if (!$this->containerExists($container) || !$this->blobExists($container, $path)) {
            throw new NotFoundException("File '$path' does not exist in storage.");
        }
        if (!empty($local_file)) {
            // write to local or temp file
            $this->getBlobAsFile($container, $path, $local_file);

            return '';
        } else {
            // get content as raw or encoded as base64 for transport
            $data = $this->getBlobData($container, $path);
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
        if (!$this->containerExists($container) || !$this->blobExists($container, $path)) {
            throw new NotFoundException("File '$path' does not exist in storage.");
        }
        $blob = $this->getBlobProperties($container, $path);
        $shortName = FileUtilities::getNameFromPath($path);
        $blob['path'] = $path;
        $blob['name'] = $shortName;
        if ($include_content) {
            $data = $this->getBlobData($container, $path);
            if ($content_as_base) {
                $data = base64_encode($data);
            }
            $blob['content'] = $data;
        }

        return $blob;
    }

    /**
     * @param string $container
     * @param string $path
     * @param bool   $download
     *
     * @throws \Exception
     * @return void
     */
    public function streamFile($container, $path, $download = false)
    {
        $params = ($download) ? ['disposition' => 'attachment'] : [];
        $this->streamBlob($container, $path, $params);
    }

    /**
     * @param string $container
     * @param string $path
     * @param array  $properties
     *
     * @throws NotFoundException
     * @throws \Exception
     * @return void
     */
    public function updateFileProperties($container, $path, $properties = [])
    {
        $path = FileUtilities::fixFolderPath($path);
        // does this file exist?
        if (!$this->fileExists($container, $path)) {
            throw new NotFoundException("Folder '$path' does not exist.");
        }
        // update the file properties
        $properties = json_encode($properties);
        $this->putBlobData($container, $path, $properties);
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
                throw new BadRequestException("File '$path' already exists.");
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
        $ext = FileUtilities::getFileExtension($path);
        $mime = FileUtilities::determineContentType($ext, $content);
        $this->putBlobData($container, $path, $content, $mime);
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
        $ext = FileUtilities::getFileExtension($path);
        $mime = FileUtilities::determineContentType($ext, '', $local_path);
        $this->putBlobFromFile($container, $path, $local_path, $mime);
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
        $this->copyBlob($container, $dest_path, $src_container, $src_path);
    }

    /**
     * @param string $container
     * @param        $path
     *
     * @return void
     * @throws \Exception
     */
    public function deleteFile($container, $path)
    {
        $this->deleteBlob($container, $path);
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
        foreach ($files as $key => $file) {
            try {
                // path is full path, name is relative to root, take either
                $path = ArrayUtils::get($file, 'path');
                $name = ArrayUtils::get($file, 'name');
                if (!empty($path)) {
                    $path = static::removeContainerFromPath($container, $path);
                } elseif (!empty($name)) {
                    $path = $root . $name;
                } else {
                    throw new BadRequestException('No path or name found for file in delete request.');
                }
                if (!empty($path)) {
                    $this->deleteFile($container, $path);
                } else {
                    throw new BadRequestException('No path or name found for file in delete request.');
                }
            } catch (\Exception $ex) {
                // error whole batch here?
                $files[$key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
            }
        }

        return $files;
    }

    /**
     * @param string           $container
     * @param string           $path
     * @param null|\ZipArchive $zip
     * @param string           $zipFileName
     * @param bool             $overwrite
     *
     * @throws \Exception
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @return string Zip File Name created/updated
     */
    public function getFolderAsZip($container, $path, $zip = null, $zipFileName = '', $overwrite = false)
    {
        $path = FileUtilities::fixFolderPath($path);
        $delimiter = '';
        if (!$this->containerExists($container)) {
            throw new BadRequestException("Can not find directory '$container'.");
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

        $results = $this->listBlobs($container, $path, $delimiter);
        foreach ($results as $blob) {
            $fullPathName = ArrayUtils::get($blob, 'name');
            $shortName = substr_replace($fullPathName, '', 0, strlen($path));
            if (empty($shortName)) {
                continue;
            }
            if ('/' == substr($fullPathName, strlen($fullPathName) - 1)) {
                // folders
                if (!$zip->addEmptyDir($shortName)) {
                    throw new InternalServerErrorException("Can not include folder '$shortName' in zip file.");
                }
            } else {
                // files
                $content = $this->getBlobData($container, $fullPathName);
                if (!$zip->addFromString($shortName, $content)) {
                    throw new InternalServerErrorException("Can not include file '$shortName' in zip file.");
                }
            }
        }
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
     * @return array
     * @throws \Exception
     */
    public function extractZipFile($container, $path, $zip, $clean = false, $drop_path = '')
    {
        if ($clean) {
            try {
                // clear out anything in this directory
                $blobs = $this->listBlobs($container, $path);
                if (!empty($blobs)) {
                    foreach ($blobs as $blob) {
                        if ((0 !== strcasecmp($path, $blob['name']))) { // not folder itself
                            $this->deleteBlob($container, $blob['name']);
                        }
                    }
                }
            } catch (\Exception $ex) {
                throw new InternalServerErrorException("Could not clean out existing directory $path.\n{$ex->getMessage()}");
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            try {
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
            } catch (\Exception $ex) {
                throw $ex;
            }
        }

        return ['name' => rtrim($path, DIRECTORY_SEPARATOR), 'path' => $path];
    }

    protected function listResource($includeProperties = false)
    {
        $out = [];

        $result = $this->getFolder($this->container, '', true, true, false, $includeProperties);

        $folders = ArrayUtils::get($result, 'folder', []);
        $files = ArrayUtils::get($result, 'file', []);

        foreach ($folders as $folder) {
            $folder['path'] = trim($folder['path'], '/');
            $out[] = $folder;
        }

        foreach ($files as $file) {
            $file['path'] = trim($file['path'], '/');
            $out[] = $file;
        }

        return $out;
    }

    /**
     * @param $container
     * @param $path
     *
     * @return string
     */
    private static function removeContainerFromPath($container, $path)
    {
        if (empty($container)) {
            return $path;
        }
        $container = FileUtilities::fixFolderPath($container);

        return substr($path, strlen($container));
    }

    // implement Blob Service

    /**
     * Check if a blob exists
     *
     * @param  string $container Container name
     * @param  string $name      Blob name
     *
     * @return boolean
     * @throws \Exception
     */
    abstract public function blobExists($container, $name);

    /**
     * @param string $container
     * @param string $name
     * @param string $data
     * @param array  $properties
     *
     * @throws \Exception
     */
    abstract public function putBlobData($container, $name, $data = null, $properties = []);

    /**
     * @param string $container
     * @param string $name
     * @param string $localFileName
     * @param array  $properties
     *
     * @throws \Exception
     */
    abstract public function putBlobFromFile($container, $name, $localFileName = null, $properties = []);

    /**
     * @param string $container
     * @param string $name
     * @param string $src_container
     * @param string $src_name
     * @param array  $properties
     *
     * @throws \Exception
     */
    abstract public function copyBlob($container, $name, $src_container, $src_name, $properties = []);

    /**
     * List blobs, all or limited by prefix or delimiter
     *
     * @param  string $container Container name
     * @param  string $prefix    Optional. Filters the results to return only blobs whose name begins with the
     *                           specified prefix.
     * @param  string $delimiter Optional. Delimiter, i.e. '/', for specifying folder hierarchy
     *
     * @return array
     * @throws \Exception
     */
    abstract public function listBlobs($container, $prefix = '', $delimiter = '');

    /**
     * Get blob
     *
     * @param  string $container     Container name
     * @param  string $name          Blob name
     * @param  string $localFileName Local file name to store downloaded blob
     *
     * @throws \Exception
     */
    abstract public function getBlobAsFile($container, $name, $localFileName = null);

    /**
     * @param string $container
     * @param string $name
     *
     * @return mixed
     * @throws \Exception
     */
    abstract public function getBlobData($container, $name);

    /**
     * List blob
     *
     * @param  string $container Container name
     * @param  string $name      Blob name
     *
     * @return array
     * @throws \Exception
     */
    abstract public function getBlobProperties($container, $name);

    /**
     * @param string $container
     * @param string $name
     * @param array  $params
     *
     * @throws \Exception
     */
    abstract public function streamBlob($container, $name, $params = []);

    /**
     * @param string $container
     * @param string $name
     *
     * @throws \Exception
     */
    abstract public function deleteBlob($container, $name);
}