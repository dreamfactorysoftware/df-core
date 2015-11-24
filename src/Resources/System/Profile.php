<?php
namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Resources\UserProfileResource;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Utility\Session;

class Profile extends UserProfileResource
{
    /** @inheritdoc */
    protected function handleGET()
    {
        if (!Session::isSysAdmin()) {
            throw new UnauthorizedException('You are not authorized to perform this action.');
        }

        return parent::handleGET();
    }

    /** @inheritdoc */
    protected function handlePOST()
    {
        if (!Session::isSysAdmin()) {
            throw new UnauthorizedException('You are not authorized to perform this action.');
        }

        return parent::handlePOST();
    }
}