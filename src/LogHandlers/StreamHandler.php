<?php
namespace DreamFactory\Core\LogHandlers;

class StreamHandler extends \Monolog\Handler\StreamHandler
{
    use DfLoggingTrait;
}