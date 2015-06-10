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

use DreamFactory\Library\Utility\ArrayUtils;

/**
 * BaseSystemLookup - an abstract base class for system lookups
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Lookup whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|Lookup whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|Lookup whereValue( $value )
 * @method static \Illuminate\Database\Query\Builder|Lookup whereDescription( $value )
 * @method static \Illuminate\Database\Query\Builder|Lookup whereIsPrivate( $value )
 * @method static \Illuminate\Database\Query\Builder|Lookup whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|Lookup whereLastModifiedDate( $value )
 */
class BaseSystemLookup extends BaseSystemModel
{
    protected $fillable = [ 'name', 'value', 'private', 'description' ];

    protected $casts = [ 'is_private' => 'boolean' ];

    protected $encrypted = [ 'value' ];

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->attributesToArray();

        if ( ArrayUtils::getBool( $attributes, 'private' ) )
        {
            $attributes['value'] = '**********';
        }

        return array_merge( $attributes, $this->relationsToArray() );
    }
}