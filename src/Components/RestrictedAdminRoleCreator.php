<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\RoleServiceAccess;
use DreamFactory\Core\Models\Service;

/**
 * Services access by tabs for admin role
 */
class RestrictedAdminRoleCreator
{
    /**
     * Default system service name
     *
     * @type int
     */
    const SYSTEM_SERVICE_NAME = "system";

    /**
     * @param array $records
     * @return array
     * @throws InternalServerErrorException
     * @throws \Exception
     */
    public static function createAndLinkRestrictedAdminRole(array $records)
    {
        $tabs = array_get($records[0], "access_by_tabs");
        $role = self::createRestrictedAdminRole(array_get($records[0], 'email'));
        $roleId = $role["id"];
        self::createRoleServiceAccess($tabs, $roleId);
        return self::linkRoleToRestrictedAdmin($records, $roleId);
    }

    /**
     * Creates role for Admin if access by tabs was specified.
     *
     * @param string $email
     * @return \DreamFactory\Core\Models\BaseModel
     * @throws \Exception
     */
    private static function createRestrictedAdminRole(string $email)
    {
        return Role::create(["name" => $email . "'s role", "description" => $email . "'s admin role", "is_active" => 1]);
    }

    /**
     * Links new role with Admin and App.
     *
     * @param       $records
     * @param       $roleId
     *
     * @return array $role
     */
    private static function linkRoleToRestrictedAdmin($records, $roleId)
    {
        $userToAppToRoleByUserId = array(["app_id" => App::whereName("admin")->first()["id"], "role_id" => $roleId]);

        if (in_array("apidocs", $records[0]["access_by_tabs"])) {
            array_push($userToAppToRoleByUserId, ["app_id" => App::whereName("api_docs")->first()["id"], "role_id" => $roleId]);
        }

        if (in_array("files", $records[0]["access_by_tabs"])) {
            array_push($userToAppToRoleByUserId, ["app_id" => App::whereName("file_manager")->first()["id"], "role_id" => $roleId]);
        }
        $records[0]["user_to_app_to_role_by_user_id"] = $userToAppToRoleByUserId;

        return $records;
    }

    /**
     * Creates role service access for given tabs
     * @param array $tabs
     * @param int $roleId
     * @throws InternalServerErrorException
     */
    private static function createRoleServiceAccess(array $tabs, int $roleId)
    {
        self::createTabServicesAccess(self::getTabsAccessesMap()["default"], $roleId);

        foreach ($tabs as $tab) {
            self::createTabServicesAccess(self::getTabsAccessesMap()[$tab], $roleId);
        }
    }

    /**
     * Creates tab to method mapper
     *
     * @return array
     */
    public static function getTabsAccessesMap()
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
    private static function getServiceIdByName(string $name)
    {
        return Service::whereName($name)->get(['id'])->first()['id'];
    }

    /**
     * @param array $access
     * @return int
     */
    private static function getServiceName(array $access)
    {
        return isset($access["serviceName"]) ? $access["serviceName"] : self::SYSTEM_SERVICE_NAME;
    }

    /**
     * create service accesses
     *
     * @param array $params
     * @param int $roleId
     * @throws InternalServerErrorException
     * @throws \Exception
     */
    private static function createTabServicesAccess(array $params, int $roleId)
    {
        foreach ($params as $access) {
            RoleServiceAccess::createUnique([
                "role_id" => $roleId,
                "service_id" => self::getServiceIdByName(self::getServiceName($access)),
                "component" => $access["component"],
                "verb_mask" => $access["verbMask"],
                "requestor_mask" => ServiceRequestorTypes::getAllRequestorTypes(),
                "filters" => [],
                "filter_op" => "AND"]);
        }
    }
}