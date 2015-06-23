<?php
namespace DreamFactory\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * InternalServerErrorException
 */
class InternalServerErrorException extends RestException
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
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, $message,
            $code ?: Response::HTTP_INTERNAL_SERVER_ERROR, $previous, $context);
    }
}