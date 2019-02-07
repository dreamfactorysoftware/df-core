<?php

namespace DreamFactory\Core\Models;

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
     * @throws InternalServerErrorException
     */
    public function createRoleServiceAccess()
    {
        $this->createDefaultRoleServiceAccess();
        $tabToMethodMapper = $this->getTabToMethodMapper();
        foreach ($this->tabs as $tab) {
            $method = $tabToMethodMapper[$tab];
            $this->$method();
        }
    }

    /**
     * Creates role services access relation for apps tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForAppsTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "app/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "service/*", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "role/*", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates role services access relation for Admins tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForAdminsTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "admin/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "role/*", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates role services access relation for Users tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForUsersTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "user/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "role/*", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "app/*", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates role services access relation for Roles tab
     *
     * @throws InternalServerErrorException
     */
    /*protected function createRoleServiceAccessForRolesTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "role/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "app/*", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }*/

    /**
     * Creates role services access relation for services tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForServicesTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "service_type/", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "service/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates role services access relation for data tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForDataTab()
    {

        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 5, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }

    }

    /**
     * Creates role services access relation for data tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForApiDocsTab()
    {

        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 2, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }

    }

    /**
     * Creates role services access relation for data tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForFilesTab()
    {

        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 3, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }

    }

    /**
     * Creates role services access relation for scripts tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForScriptsTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "event/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "event_script/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "script_type/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates role services access relation for Config tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForConfigTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "cache/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "cors/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "email_template/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "lookup/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 2, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 3, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 4, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 6, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 7, "component" => "*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates role services access relation for Packages tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForPackagesTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "package/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates role services access relation for Limits tab
     *
     * @throws InternalServerErrorException
     */
    protected function createRoleServiceAccessForLimitsTab()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "limit/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "limit_cache/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "user/", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "role/", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "service/", "verb_mask" => 1, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
        }
    }

    /**
     * Creates default role services access relation
     *
     * @throws InternalServerErrorException
     */
    protected function createDefaultRoleServiceAccess()
    {
        try {
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "custom/*", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "admin/profile", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
            RoleServiceAccess::createUnique(["role_id" => $this->roleId, "service_id" => 1, "component" => "admin/password", "verb_mask" => 31, "requestor_mask" => 3, "filters" => [], "filter_op" => "AND"]);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field . {$ex->getMessage()}");
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
            $role = Role::whereId($roleId);
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
    private function getTabToMethodMapper()
    {
        return array(
            "apps" => "createRoleServiceAccessForAppsTab",
            "admins" => "createRoleServiceAccessForAdminsTab",
            "users" => "createRoleServiceAccessForUsersTab",
            "services" => "createRoleServiceAccessForServicesTab",
            "apidocs" => "createRoleServiceAccessForApiDocsTab",
            "schema/data" => "createRoleServiceAccessForDataTab",
            "files" => "createRoleServiceAccessForFilesTab",
            "scripts" => "createRoleServiceAccessForScriptsTab",
            "config" => "createRoleServiceAccessForConfigTab",
            "packages" => "createRoleServiceAccessForPackagesTab",
            "limits" => "createRoleServiceAccessForLimitsTab"
        );
    }
}