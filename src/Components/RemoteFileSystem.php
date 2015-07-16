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
        $_out = [];

        if (!empty($containers)) {
            if (!isset($containers[0])) {
                // single folder, make into array
                $containers = [$containers];
            }
            foreach ($containers as $_key => $_folder) {
                try {
                    // path is full path, name is relative to root, take either
                    $_out[$_key] = $this->createContainer($_folder, $check_exist);
                } catch (\Exception $ex) {
                    // error whole batch here?
                    $_out[$_key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
                }
            }
        }

        return $_out;
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
            foreach ($containers as $_key => $_folder) {
                try {
                    // path is full path, name is relative to root, take either
                    $_name = ArrayUtils::get($_folder, 'name', trim(ArrayUtils::get($_folder, 'path'), '/'));
                    if (!empty($_name)) {
                        $this->deleteContainer($_name, $force);
                    } else {
                        throw new DfException('No name found for container in delete request.');
                    }
                } catch (\Exception $ex) {
                    // error whole batch here?
                    $containers[$_key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
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
     * @param bool   $include_properties
     *
     * @throws NotFoundException
     * @return array
     */
    public function getFolder(
        $container,
        $path,
        $include_files = true,
        $include_folders = true,
        $full_tree = false,
        $include_properties = false
    ){
        if (!empty($path)) {
            $path = FileUtilities::fixFolderPath($path);
            $_shortName = FileUtilities::getNameFromPath($path);
            //$_out = ['container' => $container, 'name' => $_shortName, 'path' => $container . '/' . $path];
            $_out = ['name' => $_shortName, 'path' => '/' . $path];
            if ($include_properties) {
                // properties
                if ($this->containerExists($container) && $this->blobExists($container, $path)) {
                    $properties = $this->getBlobProperties($container, $path);
                    $_out = array_merge($properties, $_out);
                }
            }
        } else {
            //$_out = ['container' => $container, 'name' => $container, 'path' => $container];
            $_out = ['name' => $container, 'path' => $container];
        }

        $_delimiter = ($full_tree) ? '' : '/';
        $_files = [];
        $_folders = [];
        if ($this->containerExists($container)) {
            if (!empty($path)) {
                if (!$this->blobExists($container, $path)) {
                    // blob may not exist for "fake" folders, i.e. S3 prefixes
//					throw new NotFoundException( "Folder '$path' does not exist in storage." );
                }
            }
            $_results = $this->listBlobs($container, $path, $_delimiter);
            foreach ($_results as $_data) {
                $_fullPathName = ArrayUtils::get($_data, 'name');
                //$_data['path'] = $container . '/' . $_fullPathName;
                $_data['path'] = '/' . $_fullPathName;
                $_data['name'] = rtrim(substr($_fullPathName, strlen($path)), '/');
                if ('/' == substr($_fullPathName, -1)) {
                    // folders
                    if ($include_folders) {
                        $_folders[] = $_data;
                    }
                } else {
                    // files
                    if ($include_files) {
                        $_files[] = $_data;
                    }
                }
            }
        } else {
            if (!empty($path)) {
                throw new NotFoundException("Folder '$path' does not exist in storage.");
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
        $_parent = FileUtilities::getParentFolder($path);
        if (!empty($_parent) && (!$this->folderExists($container, $_parent))) {
            if ($check_exist) {
                throw new NotFoundException("Folder '$_parent' does not exist.");
            }
            $this->createFolder($container, $_parent, $is_public, $properties, false);
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
        $_parent = FileUtilities::getParentFolder($dest_path);
        if (!empty($_parent) && (!$this->folderExists($container, $_parent))) {
            throw new NotFoundException("Folder '$_parent' does not exist.");
        }
        // create the folder
        $this->checkContainerForWrite($container); // need to be able to write to storage
        $this->copyBlob($container, $dest_path, $src_container, $src_path);
        // now copy content of folder...
        $_blobs = $this->listBlobs($src_container, $src_path);
        if (!empty($_blobs)) {
            foreach ($_blobs as $_blob) {
                $_srcName = ArrayUtils::get($_blob, 'name');
                if ((0 !== strcasecmp($src_path, $_srcName))) {
                    // not self properties blob
                    $_name = FileUtilities::getNameFromPath($_srcName);
                    $_fullPathName = $dest_path . $_name;
                    $this->copyBlob($container, $_fullPathName, $src_container, $_srcName);
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
        $_blobs = $this->listBlobs($container, $path);
        if (!empty($_blobs)) {
            if ((1 === count($_blobs)) && (0 === strcasecmp($path, $_blobs[0]['name']))) {
                // only self properties blob
            } else {
                if (!$force) {
                    throw new BadRequestException("Folder '$path' contains other files or folders.");
                }
                foreach ($_blobs as $_blob) {
                    $_name = ArrayUtils::get($_blob, 'name');
                    $this->deleteBlob($container, $_name);
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
        foreach ($folders as $_key => $_folder) {
            try {
                // path is full path, name is relative to root, take either
                $_path = ArrayUtils::get($_folder, 'path');
                $_name = ArrayUtils::get($_folder, 'name');
                if (!empty($_path)) {
                    $_path = static::removeContainerFromPath($container, $_path);
                } elseif (!empty($_name)) {
                    $_path = $root . $_folder['name'];
                } else {
                    throw new BadRequestException('No path or name found for folder in delete request.');
                }
                if (!empty($_path)) {
                    $this->deleteFolder($container, $_path, $force);
                } else {
                    throw new BadRequestException('No path or name found for folder in delete request.');
                }
            } catch (\Exception $ex) {
                // error whole batch here?
                $folders[$_key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
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
            $_data = $this->getBlobData($container, $path);
            if ($content_as_base) {
                $_data = base64_encode($_data);
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
    public function getFileProperties($container, $path, $include_content = false, $content_as_base = true)
    {
        if (!$this->containerExists($container) || !$this->blobExists($container, $path)) {
            throw new NotFoundException("File '$path' does not exist in storage.");
        }
        $_blob = $this->getBlobProperties($container, $path);
        $_shortName = FileUtilities::getNameFromPath($path);
        //$_blob['path'] = $container . '/' . $path;
        $_blob['path'] = '/' . $path;
        $_blob['name'] = $_shortName;
        if ($include_content) {
            $data = $this->getBlobData($container, $path);
            if ($content_as_base) {
                $data = base64_encode($data);
            }
            $_blob['content'] = $data;
        }

        return $_blob;
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
        $_params = ($download) ? ['disposition' => 'attachment'] : [];
        $this->streamBlob($container, $path, $_params);
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
        $_parent = FileUtilities::getParentFolder($path);
        if (!empty($_parent) && (!$this->folderExists($container, $_parent))) {
            throw new NotFoundException("Folder '$_parent' does not exist.");
        }

        // create the file
        $this->checkContainerForWrite($container); // need to be able to write to storage
        if ($content_is_base) {
            $content = base64_decode($content);
        }
        $_ext = FileUtilities::getFileExtension($path);
        $_mime = FileUtilities::determineContentType($_ext, $content);
        $this->putBlobData($container, $path, $content, $_mime);
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
        $_parent = FileUtilities::getParentFolder($path);
        if (!empty($_parent) && (!$this->folderExists($container, $_parent))) {
            throw new NotFoundException("Folder '$_parent' does not exist.");
        }

        // create the file
        $this->checkContainerForWrite($container); // need to be able to write to storage
        $_ext = FileUtilities::getFileExtension($path);
        $_mime = FileUtilities::determineContentType($_ext, '', $local_path);
        $this->putBlobFromFile($container, $path, $local_path, $_mime);
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
        $_parent = FileUtilities::getParentFolder($dest_path);
        if (!empty($_parent) && (!$this->folderExists($container, $_parent))) {
            throw new NotFoundException("Folder '$_parent' does not exist.");
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
        foreach ($files as $_key => $_file) {
            try {
                // path is full path, name is relative to root, take either
                $_path = ArrayUtils::get($_file, 'path');
                $_name = ArrayUtils::get($_file, 'name');
                if (!empty($_path)) {
                    $_path = static::removeContainerFromPath($container, $_path);
                } elseif (!empty($_name)) {
                    $_path = $root . $_name;
                } else {
                    throw new BadRequestException('No path or name found for file in delete request.');
                }
                if (!empty($_path)) {
                    $this->deleteFile($container, $_path);
                } else {
                    throw new BadRequestException('No path or name found for file in delete request.');
                }
            } catch (\Exception $ex) {
                // error whole batch here?
                $files[$_key]['error'] = ['message' => $ex->getMessage(), 'code' => $ex->getCode()];
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
        $_delimiter = '';
        if (!$this->containerExists($container)) {
            throw new BadRequestException("Can not find directory '$container'.");
        }
        $_needClose = false;
        if (!isset($zip)) {
            $_needClose = true;
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

        $_results = $this->listBlobs($container, $path, $_delimiter);
        foreach ($_results as $_blob) {
            $_fullPathName = ArrayUtils::get($_blob, 'name');
            $_shortName = substr_replace($_fullPathName, '', 0, strlen($path));
            if (empty($_shortName)) {
                continue;
            }
            if ('/' == substr($_fullPathName, strlen($_fullPathName) - 1)) {
                // folders
                if (!$zip->addEmptyDir($_shortName)) {
                    throw new InternalServerErrorException("Can not include folder '$_shortName' in zip file.");
                }
            } else {
                // files
                $_content = $this->getBlobData($container, $_fullPathName);
                if (!$zip->addFromString($_shortName, $_content)) {
                    throw new InternalServerErrorException("Can not include file '$_shortName' in zip file.");
                }
            }
        }
        if ($_needClose) {
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
                $_blobs = $this->listBlobs($container, $path);
                if (!empty($_blobs)) {
                    foreach ($_blobs as $_blob) {
                        if ((0 !== strcasecmp($path, $_blob['name']))) { // not folder itself
                            $this->deleteBlob($container, $_blob['name']);
                        }
                    }
                }
            } catch (\Exception $ex) {
                throw new InternalServerErrorException("Could not clean out existing directory $path.\n{$ex->getMessage()}");
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            try {
                $_name = $zip->getNameIndex($i);
                if (empty($_name)) {
                    continue;
                }
                if (!empty($drop_path)) {
                    $_name = str_ireplace($drop_path, '', $_name);
                }
                $fullPathName = $path . $_name;
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

        //return ['folder' => ['name' => rtrim($path, DIRECTORY_SEPARATOR), 'path' => $container . '/' . $path]];
        return ['folder' => ['name' => rtrim($path, DIRECTORY_SEPARATOR), 'path' => '/' . $path]];
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