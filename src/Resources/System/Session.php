<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Resources\UserSessionResource;
use DreamFactory\Core\Exceptions\NotFoundException;

class Session extends UserSessionResource
{
    /**
     * {@inheritdoc}
     */
    protected function handleGET()
    {
        $user = \Auth::user();

        if (empty($user)) {
            throw new NotFoundException('No user session found.');
        }

        if (false === Scalar::boolval($user->is_sys_admin)) {
            throw new UnauthorizedException('You are not authorized to perform this action.');
        }

        return parent::handleGET();
    }

    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $credentials = [
            'email'        => $this->getPayloadData('email'),
            'password'     => $this->getPayloadData('password'),
            'is_sys_admin' => 1
        ];

        return $this->handleLogin($credentials, Scalar::boolval($this->getPayloadData('remember_me')));
    }
}