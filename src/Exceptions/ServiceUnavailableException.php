<?php
namespace DreamFactory\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * ServiceUnavailableException
 */
class ServiceUnavailableException extends RestException
{
    /**
     * Constructor.
     *
     * @param string  $message error message
     * @param integer $code    error code
     * @param mixed   $previous
     * @param mixed   $context Additional information for downstream consumers
     */
    public function __construct($message = null, $code = null, $previous = null, $context = null)
    {
        parent::__construct(Response::HTTP_SERVICE_UNAVAILABLE, $message, $code ?: Response::HTTP_SERVICE_UNAVAILABLE,
            $previous, $context);
    }
}