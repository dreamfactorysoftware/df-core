<?php

namespace DreamFactory\Core\Services\Email;

use DreamFactory\Library\Utility\ArrayUtils;
use Illuminate\Mail\Transport\MailgunTransport;

class MailGun extends BaseService
{
    protected function setTransport($config)
    {
        $domain = ArrayUtils::get($config, 'domain');
        $key = ArrayUtils::get($config, 'key');

        $this->transport = static::getTransport($domain, $key);
    }

    /**
     * @param $domain
     * @param $key
     *
     * @return \Illuminate\Mail\Transport\MailgunTransport
     */
    public static function getTransport($domain, $key)
    {
        return new MailgunTransport($key, $domain);
    }
}