<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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

namespace DreamFactory\Core\Models;

/**
 * Setting
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Setting whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|Setting whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|Setting whereValue( $value )
 * @method static \Illuminate\Database\Query\Builder|Setting whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|Setting whereLastModifiedDate( $value )
 */
class Setting extends BaseSystemModel
{
    protected $table = 'system_setting';

}