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

use Symfony\Component\HttpFoundation\Response;

/**
 * BadRequestException
 */
class BadRequestException extends RestException
{
	/**
	 * Constructor.
	 *
	 * @param string  $message error message
	 * @param integer $code    error code
	 * @param mixed   $previous
	 * @param mixed   $context Additional information for downstream consumers
	 */
	public function __construct( $message = null, $code = null, $previous = null, $context = null )
	{
		parent::__construct( Response::HTTP_BAD_REQUEST, $message, $code ? : Response::HTTP_BAD_REQUEST, $previous, $context );
	}
}