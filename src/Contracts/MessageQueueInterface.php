<?php

namespace DreamFactory\Core\Contracts;

interface MessageQueueInterface
{
    public function subscribe(array $payload);

    public function publish(array $data);
}