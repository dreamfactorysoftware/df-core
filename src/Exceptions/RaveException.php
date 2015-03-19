<?php
/**
 * This file is part of the DreamFactory RAVE(tm) Common
 *
 * DreamFactory RAVE(tm) Common <http://github.com/dreamfactorysoftware/rave-common>
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

/**
 * RaveException
 */
class RaveException extends \Exception
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var mixed
     */
    protected $context = null;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Constructs a exception.
     *
     * @param mixed $message
     * @param int   $code
     * @param mixed $previous
     * @param mixed $context Additional information for downstream consumers
     */
    public function __construct( $message = null, $code = null, $previous = null, $context = null )
    {
        //	If an exception is passed in, translate...
        if ( null === $code && $message instanceof \Exception )
        {
            $context = $code;

            $_exception = $message;
            $message = $_exception->getMessage();
            $code = $_exception->getCode();
            $previous = $_exception->getPrevious();
        }

        $this->context = $context;
        parent::__construct( $message, $code, $previous );
    }

    /**
     * Return a code/message combo when printed.
     *
     * @return string
     */
    public function __toString()
    {
        return '[' . $this->getCode() . '] ' . $this->getMessage();
    }

    /**
     * Get the additional context information.
     *
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set or override the additional context information.
     *
     * @param mixed $context
     *
     * @return mixed
     */
    public function setContext( $context = null )
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Set or override the message information.
     *
     * @param mixed $message
     *
     * @return mixed
     */
    public function setMessage( $message = null )
    {
        $this->message = $message;

        return $this;
    }
}
