<?php
/**
 * This file is part of the DreamFactory Rave(tm) Common
 *
 * DreamFactory Rave(tm) Common <http://github.com/dreamfactorysoftware/rave>
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
namespace DreamFactory\Rave\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * DbFilterOperators
 * DB server-side filter operator string constants
 */
class DbFilterOperators extends FactoryEnum
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const EQ = '=';
	/**
	 * @var string
	 */
	const NE = '!=';
	/**
	 * @var string
	 */
	const GT = '>';
	/**
	 * @var string
	 */
	const GE = '>=';
	/**
	 * @var string
	 */
	const LT = '<';
	/**
	 * @var string
	 */
	const LE = '<=';
	/**
	 * @var string
	 */
	const IN = 'in';
	/**
	 * @var string
	 */
	const NOT_IN = 'not in';
	/**
	 * @var string
	 */
	const STARTS_WITH = 'starts with';
    /**
     * @var string
     */
    const ENDS_WITH = 'ends with';
    /**
     * @var string
     */
    const CONTAINS = 'contains';
    /**
     * @var string
     */
    const IS_NULL = 'is null';
    /**
     * @var string
     */
    const IS_NOT_NULL = 'is not null';
    /**
     * @var string
     */
    const DOES_EXIST = 'does exist';
    /**
     * @var string
     */
    const DOES_NOT_EXIST = 'does not exist';

}
