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
     * Does this handler store any of the configuration itself, or does it expect the service object to do it all.
     * If true, expect the storeConfig to be called after service creation.
     *
     * @return boolean
     */
    public static function handlesStorage();

    /**
     * If handlesStorage returns true, this method is called so the handler can store any of the configuration needed.
     *
     * @param int   $id     The Service model key value
     * @param array $config The configuration to be handled
     *
     */
    public static function storeConfig($id, $config);

    /**
     * This method is called to validate, format, and create or update the configuration storage.
     * To store full or partial configuration in the service table, return it formatted for storage.
     *
     * @param int        $id           The Service model key value, null if this is a new service
     * @param array      $config       The configuration to be handled
     * @param array|null $local_config Any existing config stored by the service table
     *
     * @return array|null Any formatted configuration to store in the service table
     * @throws \Exception Detailed exception as to why the config isn't valid.
     */
    public static function setConfig($id, $config, $local_config = null);

    /**
     * This method is called to retrieve the configuration storage to be presented to clients.
     *
     * @param int        $id           The Service model key value
     * @param array|null $local_config Any config stored by the service table itself
     * @param boolean    $protect      Mask any sensitive data like passwords.
     *                                 Set to false when data is used for initializing a service.
     *
     * @return array|null The formatted and validated configuration for the service id, or null if not found
     */
    public static function getConfig($id, $local_config = null, $protect = true);

    /**
     * If handlesStorage is true, this method is called to delete the configuration storage.
     * If foreign keys are used against the service, this call may require no action.
     *
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