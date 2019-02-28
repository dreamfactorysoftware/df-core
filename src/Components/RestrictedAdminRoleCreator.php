<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\RoleServiceAccess;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\UserAppRole;

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
     * User email.
     *
     * @type string
     */
    protected $email = "";

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
     * @param string $email
     */
    public function __construct(string $email, array $tabs = [])
    {
        $this->email = $email;
        $this->tabs = $tabs;
    }

    /**
     * Create role and its service accesses.
     *
     * @throws \Exception
     */
    public function createRestrictedAdminRole()
    {

        $role = Role::create(["name" => $this->email . "'s role", "description" => $this->email . "'s admin role", "is_active" => 1,
            "role_service_access_by_role_id" => $this->getRoleServiceAccess($this->tabs)]);
        $this->roleId = $role["id"];
    }

    /**
     * Update role and its service accesses.
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public function updateRestrictedAdminRole()
    {
        $role = $this->getRole();
        if (isset($role["id"])) {
            $this->roleId = $role["id"];
            $roleData = Role::selectById($role["id"], ["related" => "role_service_access_by_role_id"]);
            $roleData["role_service_access_by_role_id"] = $this->getUpdatedRoleAccesses($roleData["role_service_access_by_role_id"], $role["id"]);
            Role::updateById($role["id"], $roleData);
        } else {
            $this->createRestrictedAdminRole();
        }
    }

    /**
     * Delete role by user id.
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public function deleteRole($userId)
    {
        $role = $this->getRole();
//        $userAppRoles = UserAppRole::whereUserId($userId)->get()->toArray();
//        $role = null;
//        if (count($userAppRoles) > 0) {
//            $role = Role::whereId($userAppRoles[0]["role_id"])->get()->toArray()[0];
//        }
        if ($role && $role["id"] > 0) {
            Role::deleteById($role["id"]);
            \Cache::flush();

        }
    }

    /**
     * Returns user_to_app_to_role_by_user_id array to links Admin with new role.
     *
     * @param int $userId
     * @param $isRestrictedAdmin
     * @return array
     */
    public function getUserAppRoleByUserId($isRestrictedAdmin, $userId = 0)
    {
        // TODO: make a method for that
        $userToAppToRoleByUserId = [];
        if ($isRestrictedAdmin && !$this->doUserAppRoleExist($userId, $this->getUserAppRoleLink("admin"))) {
            $userToAppToRoleByUserId[] = $this->getUserAppRoleLink("admin");
        } elseif (!$isRestrictedAdmin && $this->doUserAppRoleExist($userId, $this->getUserAppRoleLink("admin"))) {
            $userToAppToRoleByUserId[] = $this->deleteUserAppRoleLink($userId, $this->getUserAppRoleLink("admin"));
        }

        // Links role to api docs and file manager apps if the tabs are specified
        $isApiDocsTabSpecified = isset($this->tabs) && in_array("apidocs", $this->tabs);
        if ($isApiDocsTabSpecified && !$this->doUserAppRoleExist($userId, $this->getUserAppRoleLink("api_docs"))) {
            $userToAppToRoleByUserId[] = $this->getUserAppRoleLink("api_docs");
        } elseif (!$isApiDocsTabSpecified && $this->doUserAppRoleExist($userId, $this->getUserAppRoleLink("api_docs"))) {
            $userToAppToRoleByUserId[] = $this->deleteUserAppRoleLink($userId, $this->getUserAppRoleLink("api_docs"));
        }

        $isFilesTabSpecified = isset($this->tabs) && in_array("files", $this->tabs);
        if ($isFilesTabSpecified && !$this->doUserAppRoleExist($userId, $this->getUserAppRoleLink("file_manager"))) {
            $userToAppToRoleByUserId[] = $this->getUserAppRoleLink("file_manager");
        } elseif (!$isFilesTabSpecified && $this->doUserAppRoleExist($userId, $this->getUserAppRoleLink("file_manager"))) {
            $userToAppToRoleByUserId[] = $this->deleteUserAppRoleLink($userId, $this->getUserAppRoleLink("file_manager"));
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
     *
     * @param int $roleId
     * @return array
     */
    public static function getAccessibleTabsByRoleId(int $roleId)
    {
        $tabsAccessesMap = self::getTabsAccessesMap(false);
        $userAppRole = UserAppRole::whereRoleId($roleId)->get()->toArray();
        if (count($userAppRole) === 0) {
            return array_keys($tabsAccessesMap);
        }
        $roleServiceAccess = RoleServiceAccess::whereRoleId($roleId)->get()->toArray();

        foreach ($tabsAccessesMap as $tabName => $tabAccesses) {
            if (!self::hasAccessToTab($roleServiceAccess, $tabAccesses)) {
                $tabsAccessesMap = self::removeTab($tabsAccessesMap, $tabName);
            }
        }

        $tabs = array_keys($tabsAccessesMap);

        return $tabs;
    }

    /**
     * Get role service access for given tabs
     *
     * @param array $tabs
     * @param bool $withDefault
     * @param bool $unique
     * @return array
     * @throws \Exception
     */
    private function getRoleServiceAccess($tabs = [], $withDefault = true, $unique = true)
    {
        $roleServiceAccess = array();
        $map = $this->getTabsAccessesMap($withDefault);

        if ($withDefault) {
            foreach ($this->getTabServicesAccess($map["default"]) as $access) {
                $roleServiceAccess = $unique ? $this->array_push_unique($access, $roleServiceAccess) : array_push($access, $roleServiceAccess);
            }
        }

        foreach ($tabs as $tab) {
            foreach ($this->getTabServicesAccess($map[$tab]) as $access) {
                $roleServiceAccess = $unique ? $this->array_push_unique($access, $roleServiceAccess) : array_push($access, $roleServiceAccess);
            }
        }
        return $roleServiceAccess;
    }

    /**
     * Delete previous role service access
     *
     * @param array $deleteTabs
     * @param array $roleServiceAccess
     * @return array
     * @throws \Exception
     */
    private function deletePreviousRoleAccess(array $deleteTabs, array $roleServiceAccess)
    {
        $deleteTabsServiceAccesses = $this->getRoleServiceAccess($deleteTabs, false);
        $serviceAccessesToDelete = [];
        foreach ($deleteTabsServiceAccesses as $deleteAccess) {
            $existingAccessesToDelete = array_filter($roleServiceAccess, function ($roleAccess) use ($deleteAccess) {
                return $this->compareAccess($roleAccess, $deleteAccess);
            });
            if (count($existingAccessesToDelete) > 1) {
                $doNotDelete = array_first($existingAccessesToDelete, function ($value) {
                    return isset($value["id"]);
                });
                if (!$doNotDelete) {
                    unset($existingAccessesToDelete[0]);
                    break;
                } elseif (($key = array_search($doNotDelete, $existingAccessesToDelete)) !== false) {
                    unset($existingAccessesToDelete[$key]);
                }
            }
            $serviceAccessesToDelete = array_merge($serviceAccessesToDelete, $existingAccessesToDelete);
        }

        foreach ($serviceAccessesToDelete as $deleteAccess) {
            foreach ($roleServiceAccess as $key => $access) {
                if ($access === $deleteAccess) {
                    $roleServiceAccess = $this->removeAccess($roleServiceAccess, $key);
                }
            }
        }

        return $roleServiceAccess;
    }

    /**
     * @param $accesses
     * @param $key
     * @return array
     */
    private function removeAccess($accesses, $key)
    {

        if (isset($accesses[$key]["id"]) && isset($accesses[$key]["role_id"])) {
            $accesses[$key]["role_id"] = null;

            return $accesses;
        } else unset($accesses[$key]);
        return $accesses;
    }

    /**
     * @param $access1
     * @param $access2
     * @return bool
     */
    private function compareAccess($access1, $access2)
    {
        return $access1["component"] === $access2["component"] && $access1["service_id"] === $access2["service_id"] && $access1["verb_mask"] === $access2["verb_mask"] && $access1["requestor_mask"] === $access2["requestor_mask"];
    }

    /**
     * Delete previous role service access
     *
     * @param array $roleServiceAccesses
     * @param $roleId
     * @return array
     * @throws \Exception
     */
    private function getUpdatedRoleAccesses(array $roleServiceAccesses, $roleId)
    {
        $accessibleTabs = self::getAccessibleTabsByRoleId($roleId);
        $tabsToAdd = array_diff($this->tabs, $accessibleTabs);
        $tabsToDelete = array_diff($accessibleTabs, $this->tabs);
        $roleServiceAccesses = array_merge($roleServiceAccesses, $this->getRoleServiceAccess($tabsToAdd, false));
        $roleServiceAccesses = $this->deletePreviousRoleAccess($tabsToDelete, $roleServiceAccesses);
        return $roleServiceAccesses;
    }

    /**
     * @param $access
     * @param array $roleServiceAccess
     * @return array
     */
    private function array_push_unique($access, array $roleServiceAccess)
    {
        if (!in_array($access, $roleServiceAccess)) {
            array_push($roleServiceAccess, $access);
        }
        return $roleServiceAccess;
    }

    /**
     * Get role by user id
     *
     * @return bool
     */
    public function getRole()
    {
        $roles = Role::whereName($this->email . "'s role")->get()->toArray();
        if (count($roles) > 0) {
            return $roles[0];
        } else {
            return null;
        }
        return Role::whereName($this->email . "'s role")->get()->toArray()[0];

        $userAppRoles = UserAppRole::whereUserId($userId)->get()->toArray();
        if (count($userAppRoles) > 0) {
            return Role::whereId($userAppRoles[0]["role_id"])->get()->toArray()[0];
        }

        return null;
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
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => "files"),
                array("component" => "*", "verbMask" => VerbsMask::getFullAccessMask(), "serviceName" => "logs"),
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
                array("component" => "package/*", "verbMask" => VerbsMask::getFullAccessMask()),
                array("component" => "app/*", "verbMask" => VerbsMask::GET_MASK),
                array("component" => "*", "verbMask" => VerbsMask::GET_MASK | VerbsMask::POST_MASK, "serviceName" => "logs"),
                array("component" => "*", "verbMask" => VerbsMask::GET_MASK | VerbsMask::POST_MASK, "serviceName" => "files"),
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
     * Look for service id by service name
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
     * Get tab service accesses
     *
     * @param array $params
     * @return array
     * @throws \Exception
     */
    private function getTabServicesAccess(array $params)
    {
        $result = array();
        foreach ($params as $access) {
            array_push($result, [
                "service_id" => self::getServiceIdByName($this->getServiceName($access)),
                "component" => $access["component"],
                "verb_mask" => $access["verbMask"],
                "requestor_mask" => ServiceRequestorTypes::getAllRequestorTypes(),
                "filters" => [],
                "filter_op" => "AND"]);
        }
        return $result;
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
     * Hide a tab by name
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
     * @param string $appName
     * @return mixed
     */
    private function getAppIdByName(string $appName)
    {
        return App::whereName($appName)->first()["id"];
    }

    /**
     * @param string $appName
     * @return mixed
     */
    private function getUserAppRoleLink(string $appName)
    {
        return ["app_id" => $this->getAppIdByName($appName), "role_id" => $this->roleId];
    }

    /**
     * @param $userId
     * @param array $userAppRoleData
     * @return mixed
     */
    private function doUserAppRoleExist($userId, array $userAppRoleData)
    {
        if ($userAppRoleData["role_id"] === 0) return UserAppRole::whereUserId($userId)->whereAppId($userAppRoleData["app_id"])->exists();
        if ($userId !== 0) return UserAppRole::whereUserId($userId)->whereAppId($userAppRoleData["app_id"])->whereRoleId($userAppRoleData["role_id"])->exists();
        return UserAppRole::whereAppId($userAppRoleData["app_id"])->whereRoleId($userAppRoleData["role_id"])->exists();
    }

    /**
     * @param $userId
     * @param array $userAppRoleData
     * @return mixed
     */
    private function deleteUserAppRoleLink($userId, array $userAppRoleData)
    {
        $userAppRole = UserAppRole::whereUserId($userId)->whereAppId($userAppRoleData["app_id"])->whereRoleId($userAppRoleData["role_id"])->get()->toArray();
        if ($userAppRoleData["role_id"] === 0)
            $userAppRole = UserAppRole::whereUserId($userId)->whereAppId($userAppRoleData["app_id"])->get()->toArray();
        if (count($userAppRole) > 0) {
            $userAppRole = $userAppRole[0];
            $userAppRole["user_id"] = null;
        }
        return $userAppRole;
    }
}