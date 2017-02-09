<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\AdminUser;

class Admin extends BaseSystemResource
{
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = AdminUser::class;

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
}