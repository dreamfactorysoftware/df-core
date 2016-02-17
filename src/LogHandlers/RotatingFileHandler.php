<?php

namespace DreamFactory\Core\LogHandlers;

class RotatingFileHandler extends \Monolog\Handler\RotatingFileHandler
{
    use DfLoggingTrait;
}