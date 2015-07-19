<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\App as AppModel;

class App extends BaseSystemResource
{
    /**
     * Handles PATCH action
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePATCH()
    {
        if (!empty($this->resource)) {
            if ($this->request->getParameterAsBool(ApiOptions::REGENERATE)) {
                /** @var AppModel $appClass */
                $appClass = $this->model;
                $app = $appClass::find($this->resource)->first();
                $app->api_key = $appClass::generateApiKey($app->name);
                $app->save();
            }
        }

        return parent::handlePATCH();
    }
}