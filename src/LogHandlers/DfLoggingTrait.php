<?php
namespace DreamFactory\Core\LogHandlers;

use Monolog\Logger;

trait DfLoggingTrait
{
    protected function write(array $record)
    {
        $allowedLogLevel = Logger::toMonologLevel(config('df.log_level'));
        $level = $record['level'];

        if ($level >= $allowedLogLevel) {
            parent::write($record);
        }
    }
}