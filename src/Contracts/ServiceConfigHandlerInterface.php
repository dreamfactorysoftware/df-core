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
     * Does this handler store the configuration itself, or does it expect the service object to do it.
     *
     * @return boolean Returns true if this handler stores the configuration
     */
    public static function handlesStorage();

    /**
     * When handlesStorage() is false, validate and format the configuration passed in during create or update
     * of the service.
     *
     * @param array $config     The configuration to be handled, modify if necessary to correct client formatting.
     * @param array $old_config Any existing configuration from the service, null if it is being created.
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     *
     * @return boolean Returns true if config is valid, expect an exception otherwise.
     */
    public static function toStorageFormat(&$config, $old_config = null);

    /**
     * When handlesStorage() is false, format the configuration from service storage so that it is usable by the client.
     *
     * @param array   $config  The configuration from storage that is to be returned to the client.
     * @param boolean $protect Mask any sensitive data like passwords.
     *                         Set to false when data is used for initializing a service.
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     *
     * @return array Returns config formatted for client if valid, expect an exception otherwise.
     */
    public static function fromStorageFormat($config, $protect = true);

    /**
     * If handlesStorage is true, validate the configuration passed in during create or update of the service.
     * If valid, expect setConfig() to be called at a later to actually store the config, final formatting before
     * storage can be done then.
     *
     * @param array   $config The configuration to be handled, modify if necessary to correct client formatting.
     * @param boolean $create A flag to indicate whether the service is being created or updated.
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     *
     * @return boolean Returns true if config is valid, expect an exception otherwise.
     */
    public static function validateConfig($config, $create = true);

    /**
     * If handlesStorage is true, this method is called to update or create the configuration storage.
     * Configuration has already been validated, but may need formatting before storing.
     *
     * @param int   $id     The Service model key value
     * @param array $config The configuration to be handled
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     */
    public static function setConfig($id, $config);

    /**
     * If handlesStorage is true, this method is called to retrieve the configuration storage.
     *
     * @param int     $id      The Service model key value
     * @param boolean $protect Mask any sensitive data like passwords.
     *                         Set to false when data is used for initializing a service.
     *
     * @return array|null The configuration value retrieved for the service id, or null if not found
     */
    public static function getConfig($id, $protect = true);

    /**
     * If handlesStorage is true, this method is called to delete the configuration storage.
     * If foreign keys are used against the service, this may require no action.
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