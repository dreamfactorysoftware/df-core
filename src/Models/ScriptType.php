<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Models;

/**
 * ScriptType
 *
 * @property string   $name
 * @property string   $class_name
 * @property string   $label
 * @property string   $description
 * @property boolean  $sandboxed
 * @method static \Illuminate\Database\Query\Builder|ScriptType whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|ScriptType whereLabel( $value )
 */
class ScriptType extends BaseModel
{
    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_date';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'last_modified_date';

    protected $table = 'script_type';

    protected $primaryKey = 'name';

    protected $fillable = [ 'name', 'class_name', 'label', 'description', 'sandboxed' ];

    public $incrementing = false;
}