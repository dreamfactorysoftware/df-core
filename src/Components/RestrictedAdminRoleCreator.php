<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\VerbsMask;
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
     * @param array $tabs
     *
     */
    public function __construct(array $tabs = [])
    {
        $this->tabs = $tabs;
    }

    /**
     * Creates role and its service accesses.
     *
     * @param string $email
     * @throws \Exception
     */
    public function createRestrictedAdminRole(string $email)
    {

        $role = Role::create(["name" => $email . "'s role", "description" => $email . "'s admin role", "is_active" => 1]);
        $this->roleId = $role["id"];
        $this->createRoleServiceAccess();
    }

    /**
     * Returns user_to_app_to_role_by_user_id array to links Admin with new role.
     *
     * @return array
     */
    public function getUserAppRoleByUserId()
    {
        $userToAppToRoleByUserId = array(["app_id" => App::whereName("admin")->first()["id"], "role_id" => $this->roleId]);

        // Links role to api docs and file manager apps if the tabs are specified
        $isApiDocsTabSpecified = isset($this->tabs) && in_array("apidocs", $this->tabs);
        if ($isApiDocsTabSpecified) {
            array_push($userToAppToRoleByUserId, ["app_id" => App::whereName("api_docs")->first()["id"], "role_id" => $this->roleId]);
        }

        $isFilesTabSpecified = isset($this->tabs) && in_array("files", $this->tabs);
        if ($isFilesTabSpecified) {
            array_push($userToAppToRoleByUserId, ["app_id" => App::whereName("file_manager")->first()["id"], "role_id" => $this->roleId]);
        }

        return $userToAppToRoleByUserId;
    }

    /**
     * Check if provided array contains all accessible tabs.
     *
     * @param array $tabs
     * @return bool
     */
    public static function isAllTabs(array $tabs)
    {
        $allAccessibleTabs = array_keys(self::getTabsAccessesMap(false));

        return $allAccessibleTabs == $tabs;
    }

    /**
     * Creates role service access for given tabs
     * @throws \Exception
     */
    private function createRoleServiceAccess()
    {
        $map = $this->getTabsAccessesMap();
        $this->createTabServicesAccess($map["default"]);

        foreach ($this->tabs as $tab) {
            $this->createTabServicesAccess($map[$tab]);
        }
    }

    /**
     * Creates tab to method mapper
     *
     * @param bool $withDefault
     * @return array
     */
    private static function getTabsAccessesMap($withDefault = true)
    {
        $map = array(
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
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => "api_docs")
            ),
            "schema/data" => array(
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => "db")
            ),
            "files" => array(
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => "files")
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
                array("component" => "", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => "logs"),
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => "email")
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
        if ($withDefault == false) {
            return array_except($map, ["default"]);
        }
        return $map;
    }

    /**
     * look for service id by service name
     *
     * @param string $name
     * @return array
     */
    private static function getServiceIdByName(string $name)
    {
        return Service::whereName($name)->get(["id"])->first()["id"];
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
     * @throws \Exception
     */
    private function createTabServicesAccess(array $params)
    {
        foreach ($params as $access) {
            RoleServiceAccess::createUnique([
                "role_id" => $this->roleId,
                "service_id" => self::getServiceIdByName($this->getServiceName($access)),
                "component" => $access["component"],
                "verb_mask" => $access["verbMask"],
                "requestor_mask" => ServiceRequestorTypes::getAllRequestorTypes(),
                "filters" => [],
                "filter_op" => "AND"]);
        }
    }

    /**
     * Does role provide access to the tab
     *
     * @param array $roleServiceAccesses
     * @param array $tabAccess
     * @return bool
     */
    private static function hasAccessToTab(array $roleServiceAccesses, array $tabAccess)
    {
        $hasAccess = true;
        foreach ($tabAccess as $access) {
            if (!self::hasAccessToServiceComponent($roleServiceAccesses, $access)) {
                $hasAccess = false;
            }
        }

        return $hasAccess;
    }


    /**
     * Compare role and tab access
     *
     * @param array $roleServiceAccesses
     * @param array $serviceComponentAccess
     * @return bool
     */
    private static function hasAccessToServiceComponent(array $roleServiceAccesses, array $serviceComponentAccess)
    {
        return boolval(array_first($roleServiceAccesses, function ($roleAccess) use ($serviceComponentAccess) {
            return $roleAccess["component"] === $serviceComponentAccess["component"] &&
                $roleAccess["verb_mask"] === $serviceComponentAccess["verbMask"] &&
                $roleAccess["service_id"] === self::getServiceIdByName(self::getServiceName($serviceComponentAccess));
        }));
    }

    /**
     * hide a tab by name
     *
     * @param array $tabs
     * @param string $tabName
     * @return array
     */
    private static function removeTab(array $tabs, string $tabName)
    {
        return array_except($tabs, $tabName);
    }

    /**
     *
     * @param int $roleId
     * @return string
     */
    public static function getAccessibleTabsByRoleId(int $roleId)
    {
        $tabsAccessesMap = self::getTabsAccessesMap();
        $roleServiceAccess = RoleServiceAccess::whereRoleId($roleId)->get()->toArray();

        //remove default as such tab doesn't exist
        $tabsAccessesMap = self::removeTab($tabsAccessesMap, "default");

        foreach ($tabsAccessesMap as $tabName => $tabAccesses) {
            if (!self::hasAccessToTab($roleServiceAccess, $tabAccesses) && $tabName !== "config") {
                $tabsAccessesMap = self::removeTab($tabsAccessesMap, $tabName);
            }
        }

        $tabs = array_keys($tabsAccessesMap);

        return $tabs;
    }
}