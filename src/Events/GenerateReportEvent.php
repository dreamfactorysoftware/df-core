<?php

namespace DreamFactory\Core\Events;

class GenerateReportEvent extends Event
{
    public function __construct($name)
    {
        parent::__construct($name);
    }

}
