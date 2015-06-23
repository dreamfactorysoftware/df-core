<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Resources\UserPasswordResource;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Exceptions\NotFoundException;

class Password extends UserPasswordResource
{
    /**
     * {@inheritdoc}
     */
    protected static function isAllowed(User $user)
    {
        if (null === $user) {
            throw new NotFoundException("User not found in the system.");
        }

        if (!$user->is_sys_admin) {
            throw new UnauthorizedException('You are not authorized to reset/change password for the account ' .
                $user->email);
        }

        return true;
    }
}