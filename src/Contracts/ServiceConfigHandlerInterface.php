<?php
namespace DreamFactory\Core\Contracts;

/**
 * Interface ServiceConfigHandlerInterface
 *
 * @package DreamFactory\Core\Contracts
 */
interface ServiceConfigHandlerInterface
{
    /**
     * @param array $config The configuration to be handled.
     * @param boolean $create A flag to indicate whether the config is being created or updated.
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     *
     * @return boolean Returns true is config is valid, false otherwise.
     */
    public static function validateConfig($config, $create=true);

    /**
     * @param int   $id     The Service model key value
     * @param array $config The configuration "field" value to be handled
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     */
    public static function setConfig($id, $config);

    /**
     * @param int $id The Service model key value
     *
     * @return array|null The configuration value retrieved for the service id, or null if not found
     */
    public static function getConfig($id);

    /**
     * @param int $id The Service model key value
     */
    public static function removeConfig($id);

    /**
     * @return array|null Returns array of available configurations for this service
     */
    public static function getAvailableConfigs();

    /**
     * @return array|null Returns array of available configuration fields and types for this service
     */
    public static function getConfigSchema();
}