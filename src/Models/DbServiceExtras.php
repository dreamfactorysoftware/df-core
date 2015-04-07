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
 * Unless required by Userlicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Models;

/**
 * DbServiceExtras
 *
 * @property integer    $id
 * @property integer    $service_id
 * @property string     $table
 * @property string     $field
 * @property string     $name_field
 * @property string     $label
 * @property string     $plural
 * @property string     $picklist
 * @property array      $validation
 * @property boolean    $user_id
 * @property boolean    $user_id_on_update
 * @property boolean    $timestamp_on_update
 * @method static \Illuminate\Database\Query\Builder|DbServiceExtras whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|DbServiceExtras whereServiceId( $value )
 */
class DbServiceExtras extends BaseSystemModel
{
    protected $table = 'db_service_extras';

}