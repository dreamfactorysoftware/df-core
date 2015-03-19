<?php
/**
 * This file is part of the DreamFactory RAVE(tm) Common
 *
 * DreamFactory RAVE(tm) Common <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2015 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Rave\Exceptions;

//use DreamFactory\Library\Utility\IfSet;
use Symfony\Component\HttpFoundation\Response;

/**
 * RestException
 * Represents an exception caused by REST API operations of end-users.
 *
 * The HTTP error code can be obtained via {@link statusCode}.
 */
class RestException extends RaveServiceException
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var int HTTP status code, such as 403, 404, 500, etc.
     */
    protected $statusCode;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Constructor.
     *
     * @param int    $status  HTTP status code, such as 404, 500, etc.
     * @param string $message error message
     * @param int    $code    error code
     * @param mixed  $previous
     * @param mixed  $context Additional information for downstream consumers
     */
    public function __construct( $status, $message = null, $code = null, $previous = null, $context = null )
    {
        $this->statusCode = $status;
        $code = $code ?: $this->statusCode;

        if ( is_null( $message ) )
        {
            $message = Response::$statusTexts[$code];
        }
        elseif ( !is_string( $message ) )
        {
            $message = strval( $message );
        }

        parent::__construct( $message, $code, $previous, $context );

        //Todo:
        error_log(
            'REST Exception #' . $code . ' > ' . $message
        /*,
        array(
            'host'        => IfSet::get( $_SERVER, 'HTTP_HOST', \gethostname() ),
            'request_uri' => IfSet::get( $_SERVER, 'REQUEST_URI' ),
            'source_ip'   => IfSet::get( $_SERVER, 'REMOTE_ADDR' ),
            'sapi_name'   => \php_sapi_name(),
        )*/
        );
    }

    /**
     * @param int $statusCode
     *
     * @return RestException
     */
    public function setStatusCode( $statusCode )
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
