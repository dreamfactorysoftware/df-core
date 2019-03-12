<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Components\RestrictedAdminController;
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

        $isRestrictedAdmin = isset($records[0]["is_restricted_admin"]) && $records[0]["is_restricted_admin"];
        $accessByTabs = isset($records[0]["access_by_tabs"]) ? $records[0]["access_by_tabs"] : [];
        if ($isRestrictedAdmin && !RestrictedAdminController::isAllTabs($accessByTabs)) {
            $restrictedAdminController = new RestrictedAdminController($records[0]["email"], $accessByTabs);
            $restrictedAdminController->createRestrictedAdminRole();

            // Links new role with admin via adding user_to_app_to_role_by_user_id array to request body
            $records[0]["user_to_app_to_role_by_user_id"] = $restrictedAdminController->getUserAppRoleByUserId($isRestrictedAdmin);
        };

        return parent::bulkCreate($records, $params);
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
        $isRestrictedAdmin = isset($records[0]["is_restricted_admin"]) && $records[0]["is_restricted_admin"];
        $accessByTabs = isset($records[0]["access_by_tabs"]) ? $records[0]["access_by_tabs"] : [];
        $restrictedAdminController = new RestrictedAdminController($records[0]["email"], $accessByTabs);
        if ($isRestrictedAdmin && !RestrictedAdminController::isAllTabs($accessByTabs)) {
            $restrictedAdminController->updateRestrictedAdminRole();

            // Links updated role with apps (admin, api_docs, file_manager) via adding user_to_app_to_role_by_user_id array to the request body
            $records[0]["user_to_app_to_role_by_user_id"] = $restrictedAdminController->getUserAppRoleByUserId($isRestrictedAdmin, $records[0]["id"]);
        } elseif (!$isRestrictedAdmin) {
            $restrictedAdminController->deleteRole($records[0]["id"]);
        };
        return parent::bulkUpdate($records, $params);
    }

    /**
     * Get Admin by email.
     *
     * @param $email
     * @return bool
     */
    public static function getAdminByEmail($email)
    {
        return self::whereEmail($email)->get()->toArray()[0];
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