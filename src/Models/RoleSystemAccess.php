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
 * RoleSystemAccess
 *
 * @property integer    $id
 * @property integer    $role_id
 * @property string     $component
 * @property integer    $verb_mask
 * @property integer    $requestor_mask
 * @property array      $filters
 * @property string     $filter_op
 * @property string     $created_date
 * @property string     $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|RoleSystemAccess whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleSystemAccess whereRoleName( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleSystemAccess whereComponent( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleSystemAccess whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleSystemAccess whereLastModifiedDate( $value )
 */
class RoleSystemAccess extends BaseSystemModel
{
    protected $table = 'role_system_accesses';

}