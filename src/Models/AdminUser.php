<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Enums\ApiOptions;
use Illuminate\Support\Arr;

class AdminUser extends User
{
    /**
     * {@inheritdoc}
     */
    public static function bulkCreate(array $records, array $params = [])
    {
        $records = static::fixRecords($records);

        $params['admin'] = true;

        if (array_get($records[0],"is_sys_admin") && isset($records[0]["access_by_tabs"])) {
            $tabs = array_get($records[0], "access_by_tabs");
            $role = self::createAdminRole($records);
            self::createRoleTabsAccess($role["id"], $tabs);
            $records = self::linkRoleToAdmin($records, $role["id"]);
        };

        return parent::bulkCreate($records, $params);
    }

    /**
     * Creates role for Admin if access by tabs was specified.
     *
     * @param       $records
     *
     * @return array $role
     */
    protected static function createAdminRole($records)
    {
        $role = Role::createInternal(["name" => array_get($records[0],'email') . "'s role", "description" => array_get($records[0],'email') . "'s admin role", "is_active" => 1]);

        return $role;
    }

    /**
     * Links new role with Admin and App.
     *
     * @param       $records
     * @param       $roleId
     *
     * @return array $role
     */
    protected static function linkRoleToAdmin($records, $roleId)
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
     * Creates role service access for specified tabs (only for restricted admins).
     *
     * @param       $roleId
     * @param array $tabs
     *
     * @throws \Exception
     */
    protected static function createRoleTabsAccess($roleId, $tabs)
    {
        $arsa = new AdminRoleServicesAccessor($roleId, $tabs);
        $arsa->createRoleServiceAccess();

    }

    /**
     * {@inheritdoc}
     */
    public static function updateById($id, array $record, array $params = [])
    {
        $record = static::fixRecords($record);

        $params['admin'] = true;

        return parent::updateById($id, $record, $params);
    }

    /**
     * {@inheritdoc}
     */
    public static function updateByIds($ids, array $record, array $params = [])
    {
        $record = static::fixRecords($record);

        $params['admin'] = true;

        return parent::updateByIds($ids, $record, $params);
    }

    /**
     * {@inheritdoc}
     */
    public static function bulkUpdate(array $records, array $params = [])
    {
        $records = static::fixRecords($records);

        $params['admin'] = true;

        return parent::bulkUpdate($records, $params);
    }

    /**
     * {@inheritdoc}
     */
    public static function deleteById($id, array $params = [])
    {
        $params['admin'] = true;

        return parent::deleteById($id, $params);
    }

    /**
     * {@inheritdoc}
     */
    public static function deleteByIds($ids, array $params = [])
    {
        $params['admin'] = true;

        return parent::deleteByIds($ids, $params);
    }

    /**
     * {@inheritdoc}
     */
    public static function bulkDelete(array $records, array $params = [])
    {
        $params['admin'] = true;

        return parent::bulkDelete($records, $params);
    }

    /**
     * {@inheritdoc}
     */
    public static function selectById($id, array $options = [], array $fields = ['*'])
    {
        $fields = static::cleanFields($fields);
        $related = array_get($options, ApiOptions::RELATED, []);
        if (is_string($related)) {
            $related = explode(',', $related);
        }
        if ($model = static::whereIsSysAdmin(1)->with($related)->find($id, $fields)) {
            return static::cleanResult($model, $fields);
        }

        return null;
    }

    /**
     * Fixes supplied records to always set is_set_admin flag to true.
     * Encrypts passwords if it is supplied.
     *
     * @param array $records
     *
     * @return array
     */
    protected static function fixRecords(array $records)
    {
        if (!Arr::isAssoc($records)) {
            foreach ($records as $key => $record) {
                $record['is_sys_admin'] = 1;
                $records[$key] = $record;
            }
        } else {
            $records['is_sys_admin'] = 1;
        }

        return $records;
    }
}