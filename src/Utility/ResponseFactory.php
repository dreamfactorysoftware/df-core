<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Components\DfResponse;
use DreamFactory\Core\Components\ExceptionResponse;
use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Exceptions\DfException;
use DreamFactory\Core\Exceptions\RestException;

/**
 * Class ResponseFactory
 *
 * @package DreamFactory\Core\Utility
 */
class ResponseFactory
{
    use ExceptionResponse;

    /**
     * @param mixed       $content
     * @param string|null $content_type
     * @param int         $status
     * @param array|null  $headers
     *
     * @return ServiceResponse
     */
    public static function create(
        $content,
        $content_type = null,
        $status = ServiceResponseInterface::HTTP_OK,
        $headers = []
    ) {
        return new ServiceResponse($content, $content_type, $status, $headers);
    }

    public static function createExceptionFromResponse(ServiceResponseInterface $response)
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
     * @param ServiceResponseInterface     $response
     * @param ServiceRequestInterface|null $request
     * @param null                         $asFile
     * @param string                       $resource
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public static function sendResponse(
        ServiceResponseInterface $response,
        ServiceRequestInterface $request = null,
        $asFile = null,
        $resource = 'resource'
    ) {
        $accepts = static::getAcceptedTypes($request);

        if (empty($asFile)) {
            $asFile = \Request::input('file');

            if (true === filter_var($asFile, FILTER_VALIDATE_BOOLEAN)) {
                $asFile = $resource . '.json';
            }

            if (is_string($asFile) && strpos($asFile, '.') !== false) {
                $format = strtolower(pathinfo($asFile, PATHINFO_EXTENSION));
                if (!empty($format)) {
                    if ($format === 'csv') {
                        $accepts = ['text/csv'];
                    } else {
                        if ($format === 'xml') {
                            $accepts = ['application/xml'];
                        } else {
                            if ($format === 'json') {
                                $accepts = ['application/json'];
                            }
                        }
                    }
                }
            } else {
                $asFile = null;
            }
        }

        // If no accepts header supplied or a blank is supplied for
        // accept header (clients like bench-rest) then use default response type from config.
        if (empty($accepts) || (isset($accepts[0]) && empty($accepts[0]))) {
            $accepts[] = config('df.default_response_type');
        }

        $status = $response->getStatusCode();
        $content = $response->getContent();
        $format = $response->getDataFormat();
        $headers = $response->getHeaders();

        if (is_null($content) && is_null($status)) {
            // No content and type specified. (File stream already handled by service)
            \Log::info('[RESPONSE] File stream');

            return null;
        }

        //  In case the status code is not a valid HTTP Status code
        if (!in_array($status, HttpStatusCodes::getDefinedConstants())) {
            //  Do necessary translation here. Default is Internal server error.
            $status = HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR;
        }

        if ($content instanceof \Exception) {
            $status =
                ($content instanceof RestException) ? $content->getStatusCode()
                    : ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR;
            $content = ['error' => self::exceptionToArray($content)];
            $format = DataFormats::PHP_ARRAY;
        }

        // check if the current content type is acceptable for return
        $contentType = $response->getContentType();
        if (empty($contentType) && is_numeric($format)) {
            $contentType = DataFormats::toMimeType($format, null);
        }

        // see if we match an accepts type, if so, go with it.
        $accepts = (array)$accepts;
        if (!empty($contentType) && static::acceptedContentType($accepts, $contentType)) {
            \Log::info('[RESPONSE]', ['Status Code' => $status, 'Content-Type' => $contentType]);
            $headers['Content-Type'] = $contentType;

            return DfResponse::create($content, $status, $headers);
        }

        // we don't have an acceptable content type, see if we can convert the content.
        $acceptsAny = false;
        $reformatted = false;
        foreach ($accepts as $acceptType) {
            $acceptFormat = DataFormats::fromMimeType($acceptType, null);
            $mimeType = (false !== strpos($acceptType, ';')) ? trim(strstr($acceptType, ';', true)) : $acceptType;
            if (is_null($acceptFormat)) {
                if ('*/*' === $mimeType) {
                    $acceptsAny = true;
                }
                continue;
            } else {
                $contentType = $mimeType;
            }
            $reformatted = DataFormatter::reformatData($content, $format, $acceptFormat);
        }

        $headers['Content-Type'] = $contentType;
        if (!empty($asFile)) {
            $headers['Content-Disposition'] = 'attachment; filename="' . $asFile . '";';
        }

        if ($acceptsAny) {
            $contentType =
                (empty($contentType)) ? DataFormats::toMimeType($format, config('df.default_response_type'))
                    : $contentType;
            $headers['Content-Type'] = $contentType;

            \Log::info('[RESPONSE]', ['Status Code' => $status, 'Content-Type' => $contentType]);

            return DfResponse::create($content, $status, $headers);
        } else {
            if (false !== $reformatted) {
                \Log::info('[RESPONSE]', ['Status Code' => $status, 'Content-Type' => $contentType]);

                return DfResponse::create($reformatted, $status, $headers);
            }
        }

        if ($status >= 400) {
            return DfResponse::create($content, $status, $headers);
        }
        $content = (is_array($content)) ? print_r($content, true) : $content;

        return DfResponse::create(
            'Content in response can not be resolved to acceptable content type. Original content: ' . $content,
            HttpStatusCodes::HTTP_BAD_REQUEST,
            $headers
        );
    }

    /**
     * @param ServiceResponseInterface $response
     *
     * @return array|mixed|string
     */
    public static function sendScriptResponse(ServiceResponseInterface $response)
    {
        $content = $response->getContent();
        $contentType = $response->getContentType();
        $format = $response->getDataFormat();
        $headers = $response->getHeaders();

        $status = $response->getStatusCode();
        //  In case the status code is not a valid HTTP Status code
        if (!in_array($status, HttpStatusCodes::getDefinedConstants())) {
            //  Do necessary translation here. Default is Internal server error.
            $status = HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR;
        }

        if ($content instanceof \Exception) {
            $status =
                ($content instanceof RestException) ? $content->getStatusCode()
                    : ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR;
            $content = ['error' => self::exceptionToArray($content)];
            $format = DataFormats::PHP_ARRAY;
        }

        return [
            'status_code'  => $status,
            'content'      => $content,
            'content_type' => $contentType,
            'format'       => $format,
            'headers'      => $headers
        ];
    }

    /**
     * @param \Exception              $e
     * @param ServiceRequestInterface $request
     *
     * @return array|mixed|string
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public static function sendException(\Exception $e, ServiceRequestInterface $request = null)
    {
        $response = static::exceptionToServiceResponse($e);

        return ResponseFactory::sendResponse($response, $request);
    }

    /**
     *
     * @param ServiceRequestInterface|null $request
     * @return array
     */
    public static function getAcceptedTypes(ServiceRequestInterface $request = null)
    {
        if ($request) {
            $accepts = $request->getParameter('accept', $request->getHeader('accept'));
        } else {
            $accepts = \Request::query('accept', \Request::header('accept'));
        }
        $accepts = array_map('trim', explode(',', $accepts));

        return $accepts;
    }

    protected static function acceptedContentType(array $accepts, $content_type)
    {
        // see if we match an accepts type, if so, go with it.
        if (!empty($content_type)) {
            foreach ($accepts as $acceptType) {
                $mimeType = (false !== strpos($acceptType, ';')) ? trim(strstr($acceptType, ';', true)) : $acceptType;
                if ((0 === strcasecmp($mimeType, $content_type)) || ('*/*' === $mimeType)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param \Exception $exception
     *
     * @return array
     */
    protected static function makeExceptionContent(\Exception $exception)
    {
        if ($exception instanceof DfException) {
            return ['error' => $exception->toArray()];
        }

        $errorInfo['code'] = ($exception->getCode()) ?: ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR;
        $errorInfo['message'] = htmlentities($exception->getMessage());

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

        return ['error' => $errorInfo];
    }
}