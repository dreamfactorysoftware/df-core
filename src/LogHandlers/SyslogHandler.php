<?php

namespace DreamFactory\Core\LogHandlers;

class SyslogHandler extends \Monolog\Handler\SyslogHandler
{
    use DfLoggingTrait;
}