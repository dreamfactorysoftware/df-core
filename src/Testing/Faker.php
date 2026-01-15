<?php

namespace DreamFactory\Core\Testing;

class Faker
{
    public function setProperties($props)
    {
        $method = debug_backtrace()[1]['function'];

        $this->{$method} = $props;
    }
}