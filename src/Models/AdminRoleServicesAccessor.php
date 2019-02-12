<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

/**
 * Services access by tabs for admin role
 */
class AdminRoleServicesAccessor
{
    /**
     * Accessible tabs.
     *
     * @type array
     */
    protected $tabs = [];

    /**
     * Role id to create service access.
     *
     * @type int
     */
    protected $roleId = 0;

    /**
     * Default system service name
     *
     * @type int
     */
    const SYSTEM_SERVICE_NAME = "system";

    /**
     * @param int $roleId
     * @param array $tabs
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function __construct($roleId, $tabs = [])
    {
        $this->tabs = $tabs;
        $this->roleId = $this->verifyRoleId($roleId);
    }

    /**
     * Creates role service access for given tabs
     * @throws \Exception
     */
    public function createRoleServiceAccess()
    {
        $this->createTabServicesAccess($this->getTabsAccessesMap()["default"]);
        foreach ($this->tabs as $tab) {
            $this->createTabServicesAccess($this->getTabsAccessesMap()[$tab]);
        }
    }


    /**
     * Verifies role id.
     *
     * @param $roleId
     *
     * @return int
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    private function verifyRoleId($roleId)
    {
        if (0 === $roleId) {
            throw new BadRequestException("Role id can't be zero.");
        }

        try {
            Role::whereId($roleId);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to get Role by id of $roleId . {$ex->getMessage()}");
        }

        return $roleId;
    }

    /**
     * Creates tab to method mapper
     *
     * @return array
     */
    private function getTabsAccessesMap()
    {
        return array(
            "apps" => array(
                array("component" => "app/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "service/*", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "role/*", "verbMask" => VerbsMask::GET_MASK)
            ),
            "admins" => array(
                array("component" => "admin/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "role/*", "verbMask" => VerbsMask::GET_MASK)
            ),
            "users" => array(
                array("component" => "user/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "role/*", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "app/*", "verbMask" => VerbsMask::GET_MASK)
            ),
            /*"roles" => array(
                array("component" => "role/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "app/*", "verbMask" => VerbsMask::GET_MASK)
            ),*/
            "services" => array(
                array("component" => "service_type/", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "service/*", "verbMask" => VerbsMask::getFullAccessMask())
            ),
            "apidocs" => array(
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => 'api_docs')
            ),
            "schema/data" => array(
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => 'db')
            ),
            "files" => array(
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => 'files')
            ),
            "scripts" => array(
                array("component" => "event/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "event_script/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "script_type/*", "verbMask" => VerbsMask::getFullAccessMask())
            ),
            "config" => array(
                array("component" => "custom/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "cache/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "cors/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "email_template/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "lookup/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => 'logs'),
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => 'email')
            ),
            "packages" => array(
                array("component" => "package/*", "verbMask" => VerbsMask::getFullAccessMask())
            ),
            "limits" => array(
                array("component" => "limit/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "limit_cache/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "user/", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "role/", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "service/", "verbMask" => VerbsMask::GET_MASK)
            ),
            "default" => array(
                array("component" => "role/*", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "admin/*", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "admin/profile", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "admin/password", "verbMask" => VerbsMask::getFullAccessMask())
            )
        );
    }

    /**
     * look for service id by service name
     *
     * @param string $name
     * @return array
     */
    private function getServiceIdByName(string $name)
    {
        return Service::whereName($name)->get(['id'])->first()['id'];
    }

    /**
     * create service accesses
     *
     * @param array $params
     * @throws \Exception
     */
    private function createTabServicesAccess(array $params)
    {
        foreach ($params as $access) {
            try {
                RoleServiceAccess::createUnique([
                    "role_id" => $this->roleId,
                    "service_id" => $this->getServiceIdByName(isset($params[0]["serviceName"]) ? $params[0]["serviceName"] : self::SYSTEM_SERVICE_NAME),
                    "component" => $access["component"],
                    "verb_mask" => $access["verbMask"],
                    "requestor_mask" => ServiceRequestorTypes::getAllRequestorTypesMask(),
                    "filters" => [],
                    "filter_op" => "AND"]);
            } catch (\Exception $ex) {
                throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
            }
        }
    }
}