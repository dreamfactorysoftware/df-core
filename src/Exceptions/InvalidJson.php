<?php
namespace DreamFactory\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * InvalidJsonException
 */
class InvalidJsonException extends BadRequestException
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
        parent::__construct($message ?: 'Invalid or malformed JSON.', $code ?: Response::HTTP_BAD_REQUEST, $previous,
            $context);
    }
}