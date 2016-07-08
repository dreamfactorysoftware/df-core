<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\User;

class Admin extends BaseSystemResource
{
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = User::class;

    protected $resources = [
        Password::RESOURCE_NAME => [
            'name'       => Password::RESOURCE_NAME,
            'class_name' => Password::class,
            'label'      => 'Password'
        ],
        Profile::RESOURCE_NAME  => [
            'name'       => Profile::RESOURCE_NAME,
            'class_name' => Profile::class,
            'label'      => 'Profile'
        ],
        Session::RESOURCE_NAME  => [
            'name'       => Session::RESOURCE_NAME,
            'class_name' => Session::class,
            'label'      => 'Session'
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleResource(array $resources)
    {
        try {
            return parent::handleResource($resources);
        } catch (NotFoundException $e) {
            if (is_numeric($this->resource)) {
                //  Perform any pre-request processing
                $this->preProcess();

                $this->response = $this->processRequest();

                if (false !== $this->response) {
                    //  Perform any post-request processing
                    $this->postProcess();
                }
                //	Inherent failure?
                if (false === $this->response) {
                    $what =
                        (!empty($this->resourcePath) ? " for resource '{$this->resourcePath}'" : ' without a resource');
                    $message =
                        ucfirst($this->action) .
                        " requests $what are not currently supported by the '{$this->name}' service.";

                    throw new BadRequestException($message);
                }

                //  Perform any response processing
                return $this->respond();
            } else {
                throw $e;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkCreate(array $records, array $params = [])
    {
        $records = static::fixRecords($records);

        $params['admin'] = true;

        return parent::bulkCreate($records, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveById($id, array $related = [])
    {
        /** @var User $modelClass */
        $modelClass = static::$model;
        $criteria = $this->getSelectionCriteria();
        $fields = array_get($criteria, 'select');
        $model = $modelClass::whereIsSysAdmin(1)->with($related)->find($id, $fields);

        $data = (!empty($model)) ? $model->toArray() : [];

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function updateById($id, array $record, array $params = [])
    {
        $record = static::fixRecords($record);

        $params['admin'] = true;

        return parent::updateById($id, $record, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function updateByIds($ids, array $record, array $params = [])
    {
        $record = static::fixRecords($record);

        $params['admin'] = true;

        return parent::updateByIds($ids, $record, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkUpdate(array $records, array $params = [])
    {
        $records = static::fixRecords($records);

        $params['admin'] = true;

        return parent::bulkUpdate($records, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteById($id, array $params = [])
    {
        $params['admin'] = true;

        return parent::deleteById($id, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteByIds($ids, array $params = [])
    {
        $params['admin'] = true;

        return parent::deleteByIds($ids, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function bulkDelete(array $records, array $params = [])
    {
        $params['admin'] = true;

        return parent::bulkDelete($records, $params);
    }

    /**
     * {@inheritdoc}
     */
    protected function getSelectionCriteria()
    {
        $criteria = parent::getSelectionCriteria();

        $condition = array_get($criteria, 'condition');

        if (!empty($condition)) {
            $condition = "($condition) AND is_sys_admin = '1' ";
        } else {
            $condition = " is_sys_admin = '1'";
        }

        $criteria['condition'] = $condition;

        return $criteria;
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

        if (ArrayUtils::isArrayNumeric($records)) {
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