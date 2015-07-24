<?php

namespace DreamFactory\Core\Services\Email;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use Swift_SmtpTransport as SmtpTransport;
use DreamFactory\Library\Utility\ArrayUtils;

class Smtp extends BaseService
{
    protected function setTransport($config)
    {
        $host = ArrayUtils::get($config, 'host');
        $port = ArrayUtils::get($config, 'port');
        $encryption = ArrayUtils::get($config, 'encryption');
        $username = ArrayUtils::get($config, 'username');
        $password = ArrayUtils::get($config, 'password');

        $this->transport = static::getTransport($host, $port, $encryption, $username, $password);
    }

    /**
     * @param $host
     * @param $port
     * @param $encryption
     * @param $username
     * @param $password
     *
     * @return \Swift_SmtpTransport
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public static function getTransport($host, $port, $encryption, $username, $password)
    {
        if (empty($host) || empty($port) || empty($encryption) || empty($username) || empty($password)) {
            throw new InternalServerErrorException("Missing one or more configuration for SMTP driver.");
        }
        $transport = SmtpTransport::newInstance($host, $port);

        if (!empty($encryption)) {
            $transport->setEncryption($encryption);
        }

        if (!empty($username) && !empty($password)) {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }

        return $transport;
    }
}