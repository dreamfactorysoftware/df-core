<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\Enums\Verbs;

class Package
{
    /** @type array */
    protected $items = [];

    /** @type string */
    protected $version = '0.1';

    /** @type string */
    protected $dfVersion = '';

    /** @type bool */
    protected $secured = false;

    /** @type string */
    protected $createdDate = '';

    /**
     * Package zip file.
     *
     * @type \ZipArchive
     */
    protected $zip = null;

    /**
     * @type zip file full path
     */
    protected $zipFilePath = null;

    private static $metaFields = ['version', 'df_version', 'description', 'secured', 'created_date'];

    public function __construct(array $manifest)
    {
        if (!static::isValid($manifest)) {
            throw new InternalServerErrorException('Invalid package manifest supplied.');
        }
        $this->version = $manifest['version'];
        $this->dfVersion = $manifest['df_version'];
        $this->secured = array_get($manifest, 'secured', false);
        $this->createdDate = array_get($manifest, 'created_date', date('Y-m-d H:i:s', time()));
        $this->items = static::getManifestItems($manifest);

        if (count($this->items) == 0) {
            throw new InternalServerErrorException('No items found in package manifest for import/export.');
        }
    }

    public function export()
    {
        $output = [];
        foreach ($this->items as $service => $resources) {
            foreach ($resources as $resource) {
                foreach ($resource as $name => $details) {
                    $api = $service . '/' . $name;
                    switch ($api) {
                        case 'system/app':
                            $output[$service][$name] = $this->exportApps($details);
                            break;
                        case 'system/role':
                            $output[$service][$name] = $this->exportRoles($details);
                            break;
                        case 'system/script':
                            $output[$service][$name] = $this->exportScripts($details);
                            break;
                        case 'system/service':
                            $output[$service][$name] = $this->exportServices($details);
                            break;
                        case $service.'/schema':
                            $output[$service][$name] = $this->exportSchemas($service, $details);
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        $output['version'] = $this->version;
        $output['df_version'] = config('df_version');

        return $output;
    }

    protected function exportSchemas($serviceName, array $tableArray)
    {
        $export = [];
        foreach($tableArray as $tableName){
            try{
                $export[] = ServiceHandler::handleRequest(Verbs::GET, $serviceName, '_schema/'.$tableName);
            } catch(NotFoundException $e){
                // Ignore 404 table not found error.
            }
        }

        return $export;
    }

    protected function exportServices(array $serviceArray)
    {
        $export = [];
        foreach ($serviceArray as $serviceId) {
            if (is_int($serviceId)) {
                /** @type Service $service */
                $service = Service::find($serviceId);
                if (!empty($service)) {
                    $export[] = $service->toArray();
                }
            } else if (is_string($serviceId)) {
                $services = Service::whereName($serviceId)->get();
                if (!empty($services)) {
                    /** @type Service $service */
                    $service = $services[0];
                    $export[] = $service->toArray();
                }
            } else {
                throw new InternalServerErrorException('Invalid Service identifier supplied. Please provide id or name.');
            }
        }

        return $export;
    }

    protected function exportScripts(array $scriptArray)
    {
        $export = [];
        foreach ($scriptArray as $scriptName) {
            if (is_string($scriptName)) {
                /** @type EventScript $eventScript */
                $eventScript = EventScript::find($scriptName);
                if (!empty($eventScript)) {
                    $export[] = $eventScript->toArray();
                }
            } else {
                throw new InternalServerErrorException('Invalid Event Script identifier supplied. Please provide event name.');
            }
        }

        return $export;
    }

    protected function exportRoles(array $roleArray)
    {
        $export = [];
        foreach ($roleArray as $roleId) {
            if (is_int($roleId)) {
                /** @type Role $role */
                $role = Role::with('role_service_access_by_role_id')->find($roleId);
                if (!empty($role)) {
                    $export[] = $role->toArray();
                }
            } else if (is_string($roleId)) {
                $roles = Role::with('role_service_access_by_role_id')->whereName($roleId)->get();
                if (!empty($roles)) {
                    /** @type Role $role */
                    $role = $roles[0];
                    $export[] = $role->toArray();
                }
            } else {
                throw new InternalServerErrorException('Invalid Role identifier supplied. Please provide id or name.');
            }
        }

        return $export;
    }

    protected function exportApps(array $appArray)
    {
        $export = [];
        foreach ($appArray as $appId) {
            if (is_int($appId)) {
                /** @type App $app */
                $app = App::find($appId);
                if (!empty($app)) {
                    $export[] = $app->toArray();
                }
            } else if (is_string($appId)) {
                $apps = App::whereName($appId)->get();
                if (!empty($apps)) {
                    /** @type App $app */
                    $app = $apps[0];
                    $export[] = $app->toArray();
                }
            } else {
                throw new InternalServerErrorException('Invalid App identifier supplied. Please provide id or name.');
            }
        }

        return $export;
    }

    /**
     * @param $m
     *
     * @return bool
     */
    protected static function isValid($m)
    {
        if (isset($m['version'], $m['df_version'])) {
            return true;
        }

        return false;
    }

    protected static function getManifestItems($m)
    {
        $items = [];
        foreach ($m as $item => $value) {
            if (!in_array($item, static::$metaFields)) {
                $items[$item] = $value;
            }
        }

        return $items;
    }

    /**
     * Initialize export zip file.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function initExportZipFile()
    {
        $host = php_uname('n');
        $filename = $host.'_'.date('Y-m-d_H:i:s', time());
        $zip = new \ZipArchive();
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $zipFileName = $tmpDir . $filename . '.zip';
        $this->zip = $zip;
        $this->zipFilePath = $zipFileName;

        if (true !== $this->zip->open($zipFileName, \ZipArchive::CREATE)) {
            throw new InternalServerErrorException('Can not create package file.');
        }

        return true;
    }
}