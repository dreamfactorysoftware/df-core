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
     * Role id to create service access.
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
            $this->createServiceAccess("app/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("service/*", VerbsMask::GET_MASK);
            $this->createServiceAccess("role/*", VerbsMask::GET_MASK);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("admin/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("role/*", VerbsMask::GET_MASK);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("user/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("role/*", VerbsMask::GET_MASK);
            $this->createServiceAccess("app/*", VerbsMask::GET_MASK);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("role/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("app/*", VerbsMask::GET_MASK);
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
            $this->createServiceAccess("service_type/", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("service/*", VerbsMask::getFullAccessMask());
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("*", VerbsMask::getFullAccessMask(), 'db');
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("*", VerbsMask::getFullAccessMask(), 'api_docs');
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("*", VerbsMask::getFullAccessMask(), 'files');
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("event/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("event_script/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("script_type/*", VerbsMask::getFullAccessMask());
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("custom/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("cache/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("cors/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("email_template/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("lookup/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("*", VerbsMask::getFullAccessMask(),'logs');
            $this->createServiceAccess("*", VerbsMask::getFullAccessMask(), 'email');
            $this->createServiceAccess("*", VerbsMask::getFullAccessMask(),'user');
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("package/*", VerbsMask::getFullAccessMask());
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("limit/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("limit_cache/*", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("user/", VerbsMask::GET_MASK);
            $this->createServiceAccess("role/", VerbsMask::GET_MASK);
            $this->createServiceAccess("service/", VerbsMask::GET_MASK);
            $this->createServiceAccess("", VerbsMask::GET_MASK);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
            $this->createServiceAccess("role/*", VerbsMask::GET_MASK);
            $this->createServiceAccess("admin/*", VerbsMask::GET_MASK);
            $this->createServiceAccess("admin/profile", VerbsMask::getFullAccessMask());
            $this->createServiceAccess("admin/password", VerbsMask::getFullAccessMask());
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Failed to create role service access field. {$ex->getMessage()}");
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
     * create service accesss
     *
     * @param string $serviceName
     * @param string $component
     * @param int $verbMask
     * @throws \Exception
     */
    private function createServiceAccess(string $component, int $verbMask, string $serviceName = self::SYSTEM_SERVICE_NAME)
    {
        RoleServiceAccess::createUnique([
            "role_id" => $this->roleId,
            "service_id" => $this->getServiceIdByName($serviceName),
            "component" => $component,
            "verb_mask" => $verbMask,
            "requestor_mask" => ServiceRequestorTypes::getFullAccessMask(),
            "filters" => [],
            "filter_op" => "AND"]);
    }
}