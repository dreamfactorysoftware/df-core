<?php

namespace DreamFactory\Core\LogHandlers;

class ErrorLogHandler extends \Monolog\Handler\ErrorLogHandler
{
    use DfLoggingTrait;
}