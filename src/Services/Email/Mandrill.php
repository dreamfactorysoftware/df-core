<?php

namespace DreamFactory\Core\Services\Email;

use DreamFactory\Library\Utility\ArrayUtils;
use Illuminate\Mail\Transport\MandrillTransport;

class Mandrill extends BaseService
{
    protected function setTransport($config)
    {
        $key = ArrayUtils::get($config, 'key');
        $this->transport = new MandrillTransport($key);
    }
}