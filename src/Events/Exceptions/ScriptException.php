<?php
namespace DreamFactory\Core\Events\Exceptions;

use DreamFactory\Core\Exceptions\InternalServerErrorException;

/**
 * Thrown when scripts exceptions are thrown
 */
class ScriptException extends InternalServerErrorException
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The buffered output at the time of the exception
     */
    protected $output;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $message The error message
     * @param string $output  The buffered output at the time of the exception, if any
     * @param int    $code
     * @param mixed  $previous
     * @param mixed  $context
     */
    public function __construct($message = null, $output = null, $code = null, $previous = null, $context = null)
    {
        $this->output = $output;

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }
}