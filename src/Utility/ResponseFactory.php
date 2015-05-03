<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Utility;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Resources\System\Config;
use Illuminate\Http\Response;
use DreamFactory\Rave\Components\RaveResponse;
use DreamFactory\Rave\Contracts\HttpStatusCodeInterface;
use DreamFactory\Rave\Enums\HttpStatusCodes;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Enums\ContentTypes;
use DreamFactory\Rave\Exceptions\RaveException;

/**
 * Class ResponseFactory
 *
 * @package DreamFactory\Rave\Utility
 */
class ResponseFactory
{
    /**
     * @param mixed  $content
     * @param string $contentType
     * @param int    $statusCode
     *
     * @return ServiceResponse
     */
    public static function create( $content, $contentType, $statusCode = ServiceResponseInterface::HTTP_OK )
    {
        return new ServiceResponse( $content, $contentType, $statusCode );
    }

    /**
     * @param ServiceResponseInterface $response
     * @param int|array                $format
     *
     * @return array|mixed|string
     */
    public static function sendResponse( ServiceResponseInterface $response, $format = ContentTypes::JSON )
    {
        $result = $response->getContent();
        $code = $response->getStatusCode();
        $responseType = $response->getContentType();

        if ( is_array( $format ) )
        {
            $json = ContentTypes::toMimeType( ContentTypes::JSON );
            if ( in_array( $json, $format ) )
            {
                $format = ContentTypes::JSON;
            }
            else
            {
                $mimeType = ArrayUtils::get( $format, 0 );
                if('*/*' === $mimeType)
                {
                    $format = ContentTypes::JSON;
                }
                else
                {
                    $format = ContentTypes::fromMimeType( $mimeType );
                }
            }
        }

        if ( empty( $result ) && empty( $responseType ) )
        {
            //No content and type specified. (File stream already handled by service)
            return;
        }

        if ( $result instanceof \Exception )
        {
            $result = self::makeExceptionContent( $result );
            $format = ContentTypes::JSON;
            $code = ( $result['error']['code'] ) ?: $code;
        }

        switch ( $format )
        {
            case ContentTypes::JSON:
                if ( ContentTypes::PHP_ARRAY === $responseType )
                {
                    //$result = DataFormatter::arrayToJson($result);
                    //Symfony Response object automatically converts this.
                }
                elseif(ContentTypes::TEXT === $responseType)
                {
                    $result = json_encode(['response'=>$result]);
                }

                $contentType = 'application/json; charset=utf-8';
                break;

            case ContentTypes::XML:
                if ( ContentTypes::XML !== $responseType )
                {
                    //Perform data conversion as needed here
                }
                $contentType = 'application/xml';
                $result = '<?xml version="1.0" ?>' . "<dfapi>\n$result</dfapi>";
                break;

            case ContentTypes::CSV:
                if ( ContentTypes::CSV !== $responseType )
                {
                    //Perform data conversion as needed here
                }
                $contentType = 'text/csv';
                break;

            default:
                $contentType = 'application/octet-stream';
                break;
        }

        //In case if the status code is not a valid HTTP Status code
        if ( !in_array( $code, HttpStatusCodes::getDefinedConstants() ) )
        {
            //do necessary translation here. Default is Internal server error.
            $code = HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR;
        }

        $ro = RaveResponse::create( $result, $code );
        $ro->headers->set( "Content-Type", $contentType );

        return $ro;
    }

    /**
     * @param $exception
     *
     * @return array
     */
    protected static function makeExceptionContent( $exception )
    {
        $code = ( $exception->getCode() ) ?: ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR;
        $context = ( $exception instanceof RaveException ) ? $exception->getContext() : null;
        $errorInfo['context'] = $context;
        $errorInfo['message'] = htmlentities( $exception->getMessage() );
        $errorInfo['code'] = $code;

        if ( "local" === env( "APP_ENV" ) )
        {
            $trace = $exception->getTraceAsString();
            $trace = str_replace( array( "\n", "#", "):" ), array( "", "<br><br>|#", "):<br>|---->" ), $trace );
            $traceArray = explode( "<br>", $trace );
            foreach ( $traceArray as $k => $v )
            {
                if ( empty( $v ) )
                {
                    $traceArray[$k] = '|';
                }
            }
            $errorInfo['trace'] = $traceArray;
        }

        $result = array(
            //Todo: $errorInfo used to be wrapped inside an array. May need to account for that for backward compatibility.
            'error' => $errorInfo
        );

        return $result;
    }
}