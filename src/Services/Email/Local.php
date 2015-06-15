<?php

namespace DreamFactory\Core\Services\Email;

use DreamFactory\Library\Utility\ArrayUtils;
use Swift_MailTransport as MailTransport;
use Swift_SendmailTransport as SendmailTransport;

class Local extends BaseService
{
    /**
     * {@inheritdoc}
     */
    protected function setTransport($config)
    {
        $driver = strtolower(ArrayUtils::get($config, 'driver', 'mail'));
        $transport = null;

        switch ($driver) {
            case 'command':
            case 'sendmail':
                $command = ArrayUtils::get($config, 'command');
                $transport = SendmailTransport::newInstance($command);
                break;
            default:
                $transport = MailTransport::newInstance();
        }

        $this->transport = $transport;
    }
}