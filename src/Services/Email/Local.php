<?php

namespace DreamFactory\Core\Services\Email;

use DreamFactory\Core\Aws\Services\Ses;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use Swift_MailTransport as MailTransport;
use Swift_SendmailTransport as SendmailTransport;

class Local extends BaseService
{
    /**
     * {@inheritdoc}
     */
    protected function setTransport($config)
    {
        $driver = \Config::get('mail.driver');
        $transport = null;

        switch ($driver) {
            case 'sendmail':
                $command = \Config::get('mail.sendmail');
                if (empty($command)) {
                    throw new InternalServerErrorException('Missing Command for sendmail driver.');
                }
                $transport = SendmailTransport::newInstance($command);

                break;
            case 'smtp':
                $host = \Config::get('mail.host');
                $port = \Config::get('mail.port');
                $encryption = \Config::get('mail.encryption');
                $username = \Config::get('mail.username');
                $password = \Config::get('mail.password');
                $transport = Smtp::getTransport($host, $port, $encryption, $username, $password);

                break;
            case 'mailgun':
                $domain = \Config::get('services.mailgun.domain');
                $key = \Config::get('services.mailgun.secret');
                $key = (!empty($key)) ? $key : \Config::get('services.mailgun.key');
                $transport = MailGun::getTransport($domain, $key);

                break;
            case 'mandrill':
                $key = \Config::get('services.mandrill.secret');
                $key = (!empty($key)) ? $key : \Config::get('services.mandrill.key');
                $transport = Mandrill::getTransport($key);
                break;
            case 'ses':
                $key = \Config::get('services.ses.key');
                $secret = \Config::get('services.ses.secret');
                $region = \Config::get('services.ses.region');
                $transport = Ses::getTransport($key, $secret, $region);

                break;
            default:
                $transport = MailTransport::newInstance();
        }

        $this->transport = $transport;
    }
}