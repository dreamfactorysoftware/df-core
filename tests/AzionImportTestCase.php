<?php

use DreamFactory\Core\Components\Package\Importer;
use DreamFactory\Core\Components\Package\Package;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Session;

/**
 * Shared setup for the package import regression tests (Azion 4.x -> 7.7 import).
 *
 * These tests write to the system database. Run them against a throwaway database.
 * Every record they create is named with PREFIX so cleanup() can remove it.
 *
 * Built-in service ids on a fresh 7.7 install: api_builder 1, agents 2, system 3,
 * api_docs 4, files 5, logs 6, db 7, email 8, user 9.
 * Before 7.7 (and on installs upgraded to 7.7): system 1, api_docs 2, files 3,
 * logs 4, db 5, email 6, user 7.
 */
abstract class AzionImportTestCase extends \DreamFactory\Core\Testing\TestCase
{
    const PREFIX = 'az_t_';

    /** @var string[] */
    protected $zips = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
        Session::setUserInfoWithJWT(User::find(1));
    }

    public function tearDown(): void
    {
        // A failed import on the unfixed code can leave its transaction open.
        while (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
        $this->cleanup();
        foreach ($this->zips as $zip) {
            if (file_exists($zip)) {
                unlink($zip);
            }
        }
        parent::tearDown();
    }

    protected function cleanup()
    {
        $like = static::PREFIX . '%';
        App::where('name', 'like', $like)->delete();
        User::where('email', 'like', $like)->delete();
        Role::where('name', 'like', $like)->delete();
        foreach (Service::where('name', 'like', $like)->get() as $service) {
            $service->delete();
        }
        if (\Schema::hasTable('event_script')) {
            \DB::table('event_script')->where('name', 'like', 'system.%' . static::PREFIX . '%')->delete();
        }
        \Cache::flush();
    }

    /**
     * Builds a package zip in code.
     *
     * @param array  $files     zip path => decoded JSON content
     * @param string $dfVersion manifest df_version
     * @param array  $manifest  manifest 'service' section
     *
     * @return string
     */
    protected function buildPackage(array $files, $dfVersion = '7.7.0', array $manifest = [])
    {
        $base = tempnam(sys_get_temp_dir(), 'dfaz_');
        unlink($base);
        $path = $base . '.zip';
        $this->zips[] = $path;

        if (empty($manifest)) {
            foreach ($files as $file => $records) {
                $resource = basename($file, '.json');
                if (strpos($file, 'system/') === 0 && is_array($records)) {
                    $key = in_array($resource, ['admin', 'user']) ? 'email' : 'name';
                    $manifest['system'][$resource] = array_values(array_filter(array_column($records, $key)));
                }
            }
        }

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('package.json', json_encode([
            'version'     => '0.1',
            'df_version'  => $dfVersion,
            'secured'     => false,
            'description' => 'azion import regression',
            'service'     => $manifest,
        ]));
        foreach ($files as $file => $records) {
            $zip->addFromString($file, is_string($records) ? $records : json_encode($records));
        }
        $zip->close();

        return $path;
    }

    /**
     * @param string $zipPath
     * @param bool   $overwrite
     *
     * @return Importer
     */
    protected function import($zipPath, $overwrite = false)
    {
        $importer = new Importer(new Package($zipPath, false), $overwrite);
        $importer->import();

        return $importer;
    }

    /**
     * @param string $zipPath
     *
     * @return Importer an importer that has not run, for calling its protected helpers.
     */
    protected function importerFor($zipPath, $overwrite = false)
    {
        return new Importer(new Package($zipPath, false), $overwrite);
    }

    protected function fileService($name, $id)
    {
        return [
            'id'        => $id,
            'name'      => $name,
            'label'     => $name,
            'type'      => 'local_file',
            'is_active' => true,
            'mutable'   => true,
            'deletable' => true,
            'config'    => ['container' => 'app'],
        ];
    }

    protected function rsa($id, $roleId, $serviceId, $component = '*', $verbMask = 1)
    {
        return [
            'id'             => $id,
            'role_id'        => $roleId,
            'service_id'     => $serviceId,
            'component'      => $component,
            'verb_mask'      => $verbMask,
            'requestor_mask' => 3,
            'filters'        => [],
            'filter_op'      => 'AND',
        ];
    }

    protected function role($id, $name, array $rsa = [], array $extra = [])
    {
        return array_merge([
            'id'                             => $id,
            'name'                           => $name,
            'description'                    => $name,
            'is_active'                      => true,
            'role_service_access_by_role_id' => $rsa,
        ], $extra);
    }

    protected function createRole($name, array $rsa = [])
    {
        $role = $this->unguarded(Role::class, ['name' => $name, 'description' => $name, 'is_active' => true]);
        foreach ($rsa as $r) {
            \DB::table('role_service_access')->insert(array_merge([
                'role_id'        => $role->id,
                'verb_mask'      => 1,
                'requestor_mask' => 3,
                'component'      => '*',
                'filter_op'      => 'AND',
            ], $r));
        }

        return $role;
    }

    /**
     * Creates a fixture record. TestCase::setUp re-guards models after the first test.
     */
    protected function unguarded($class, array $attributes)
    {
        return \Illuminate\Database\Eloquent\Model::unguarded(function () use ($class, $attributes) {
            return $class::create($attributes);
        });
    }

    protected function grants($roleName)
    {
        $roleId = Role::whereName($roleName)->value('id');

        return \DB::table('role_service_access')
            ->leftJoin('service', 'service.id', '=', 'role_service_access.service_id')
            ->where('role_id', $roleId)
            ->orderBy('role_service_access.id')
            ->get(['role_service_access.id', 'service.name as service', 'component', 'verb_mask'])
            ->map(function ($r) {
                return (array)$r;
            })
            ->all();
    }

    protected function grantServices($roleName)
    {
        return array_map(function ($g) {
            return $g['service'] . ':' . $g['component'];
        }, $this->grants($roleName));
    }

    protected function logged(Importer $importer, $level, $needle)
    {
        foreach (array_get($importer->getLog(), $level, []) as $msg) {
            if (strpos($msg, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
