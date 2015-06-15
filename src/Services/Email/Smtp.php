<?php

namespace DreamFactory\Core\Services\Email;

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

        $transport = SmtpTransport::newInstance($host, $port);

        if (!empty($encryption)) {
            $transport->setEncryption($encryption);
        }

        if (!empty($username) && !empty($password)) {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }

        $this->transport = $transport;
    }
}