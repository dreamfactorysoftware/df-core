<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Components\DfResponse;
use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Exceptions\DfException;

/**
 * Class ResponseFactory
 *
 * @package DreamFactory\Core\Utility
 */
class ResponseFactory
{
    /**
     * @param mixed       $content
     * @param int         $format
     * @param int         $status
     * @param string|null $content_type
     *
     * @return ServiceResponse
     */
    public static function create(
        $content,
        $format = DataFormats::PHP_ARRAY,
        $status = ServiceResponseInterface::HTTP_OK,
        $content_type = null
    ){
        return new ServiceResponse($content, $format, $status, $content_type);
    }

    /**
     * @param ServiceResponseInterface $response
     * @param null|string|array        $accepts
     *
     * @return array|mixed|string
     * @throws BadRequestException
     */
    public static function sendResponse(ServiceResponseInterface $response, $accepts = null)
    {
        if (empty($accepts)) {
            $accepts = array_map('trim', explode(',', \Request::header('ACCEPT')));
        }

        $content = $response->getContent();
        $format = $response->getContentFormat();

        if (empty($content) && is_null($format)) {
            // No content and type specified. (File stream already handled by service)
            return null;
        }

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
            $content = self::makeExceptionContent($content);
            $format = DataFormats::PHP_ARRAY;
        }

        // check if the current content type is acceptable for return
        $contentType = $response->getContentType();
        if (empty($contentType)) {
            $contentType = DataFormats::toMimeType($format, null);
        }

        // see if we match an accepts type, if so, go with it.
        $accepts = ArrayUtils::clean($accepts);
        if (!empty($contentType) && static::acceptedContentType($accepts, $contentType)) {
            return DfResponse::create($content, $status, ["Content-Type" => $contentType]);
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
            }

            $reformatted = DataFormatter::reformatData($content, $format, $acceptFormat);
        }

        if ($acceptsAny) {
            $contentType = (empty($contentType)) ? DataFormats::toMimeType($format, 'application/json') : $contentType;

            return DfResponse::create($content, $status, ["Content-Type" => $contentType]);
        } else if (false !== $reformatted) {
            return DfResponse::create($reformatted, $status, ["Content-Type" => $contentType]);
        }

        throw new BadRequestException('Content in response can not be resolved to acceptable content type.');
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
        $code = ($exception->getCode()) ?: ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR;
        $context = ($exception instanceof DfException) ? $exception->getContext() : null;
        $errorInfo['context'] = $context;
        $errorInfo['message'] = htmlentities($exception->getMessage());
        $errorInfo['code'] = $code;

        if ("local" === env("APP_ENV")) {
            $trace = $exception->getTraceAsString();
            $trace = str_replace(["\n", "#", "):"], ["", "<br><br>|#", "):<br>|---->"], $trace);
            $traceArray = explode("<br>", $trace);
            foreach ($traceArray as $k => $v) {
                if (empty($v)) {
                    $traceArray[$k] = '|';
                }
            }
            $errorInfo['trace'] = $traceArray;
        }

        $result = [
            //Todo: $errorInfo used to be wrapped inside an array. May need to account for that for backward compatibility.
            'error' => $errorInfo
        ];

        return $result;
    }

    /**
     * @param \Exception               $e
     * @param \Illuminate\Http\Request $request
     *
     * @return array|mixed|string
     */
    public static function getException($e, $request)
    {
        $response = ResponseFactory::create($e);
        $accepts = explode(',', $request->header('ACCEPT'));

        return ResponseFactory::sendResponse($response, $accepts);
    }
}