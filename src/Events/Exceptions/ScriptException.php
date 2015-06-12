<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
    protected $_output;

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
    public function __construct( $message = null, $output = null, $code = null, $previous = null, $context = null )
    {
        $this->_output = $output;

        parent::__construct( $message, $code, $previous, $context );
    }

    /**
     * @return string
     */
    public function getOutput()
    {
        return $this->_output;
    }
}