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

namespace DreamFactory\Rave\Models;

/**
 * UserLookup
 *
 * @property integer $id
 * @property integer $user_id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereUserId( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereValue( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereDescription( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereIsPrivate( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereLastModifiedDate( $value )
 */
class UserLookup extends BaseSystemModel
{
    protected $table = 'user_lookup';

    protected $fillable = ['user_id', 'name', 'value', 'private', 'description'];
}