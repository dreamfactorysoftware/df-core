<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\DfException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Utility\ServiceResponse;

/**
 * Class ExceptionResponse
 *
 * @package DreamFactory\Core\Components
 */
trait ExceptionResponse
{
    public static function exceptionToServiceResponse(\Exception $exception)
    {
        $status = ($exception instanceof RestException)
            ?
            $exception->getStatusCode()
            :
            ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR;
        $content = ['error' => self::exceptionToArray($exception)];

        return new ServiceResponse($content, null, $status);
    }

    public static function exceptionFromResponse(ServiceResponseInterface $response)
    {
        $message = 'Unknown Exception';
        $code = 0;

        if (is_array($content = $response->getContent())) {
            $code = array_get($content, 'error.code', $code);
            $message = array_get($content, 'error.message', $message);
        }

        return new RestException($response->getStatusCode(), $message, $code);
    }

    /**
     * @param \Exception $exception
     *
     * @return array
     */
    public static function exceptionToArray(\Exception $exception)
    {
        if ($exception instanceof DfException) {
            return $exception->toArray();
        }

        $errorInfo['code'] = ($exception->getCode()) ?: ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR;
        $errorInfo['message'] = htmlentities($exception->getMessage(), ENT_COMPAT);

        if (config('app.debug', false)) {
            $trace = $exception->getTraceAsString();
            $trace = str_replace(["\n", "#"], ["", "<br>"], $trace);
            $traceArray = explode("<br>", $trace);
            $cleanTrace = [];
            foreach ($traceArray as $k => $v) {
                if (!empty($v)) {
                    $cleanTrace[] = $v;
                }
            }
            $errorInfo['trace'] = $cleanTrace;
        }

        return $errorInfo;
    }
}
