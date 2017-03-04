<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * ErrorCodes, > 1000 for clearance of HTTP status codes
 * Use for DreamFactory error conditions
 */
class ErrorCodes extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const BATCH_ERROR = 1000;
}
