<?php

namespace DreamFactory\Core\Services\Email;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use GuzzleHttp\Client;
use Illuminate\Mail\Transport\MailgunTransport;

class MailGun extends BaseService
{
    protected function setTransport($config)
    {
        $domain = array_get($config, 'domain');
        $key = array_get($config, 'key');

        $this->transport = static::getTransport($domain, $key);
    }

    /**
     * @param $domain
     * @param $key
     *
     * @return \Illuminate\Mail\Transport\MailgunTransport
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public static function getTransport($domain, $key)
    {
        if (empty($domain) || empty($key)) {
            throw new InternalServerErrorException('Missing one or more configuration for MailGun service.');
        }

        return new MailgunTransport(new Client(), $key, $domain);
    }
}