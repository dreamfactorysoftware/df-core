<?php
namespace DreamFactory\Core\Contracts;

interface FileServiceInterface
{
    /**
     * Get File System driver
     *
     * @return FileSystemInterface
     */
    public function driver();

    /**
     * @return string
     */
    public function getContainerId();

    /**
     * @return string
     */
    public function getFilePath();

    /**
     * @return string
     */
    public function getFolderPath();

    /**
     * @return array
     */
    public function getPublicPaths();

    /**
     * @param string $container
     * @param string $path
     * @param bool   $download
     */
    public function streamFile($container, $path, $download = false);

    /**
     * @param            $container
     * @param            $path
     * @param            $zip
     * @param bool|false $clean
     * @param null       $drop_path
     *
     * @return array
     */
    public function extractZipFile($container, $path, $zip, $clean = false, $drop_path = null);
}