<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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
namespace DreamFactory\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * NotFoundException
 */
class NotFoundException extends RestException
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
		parent::__construct( Response::HTTP_NOT_FOUND, $message, $code ? : Response::HTTP_NOT_FOUND, $previous, $context );
	}
}