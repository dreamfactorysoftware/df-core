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
 * EventScript
 *
 * @property string     $name
 * @property string     $type
 * @property string     $content
 * @property string     $config
 * @property boolean    $is_active
 * @property boolean    $affects_process
 * @method static \Illuminate\Database\Query\Builder|EventScript whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|EventScript whereType( $value )
 */
class EventScript extends BaseSystemModel
{
    /**
     * @const string The private cache file
     */
    const CACHE_PREFIX = 'script.';
    /**
     * @const integer How long a EventScript cache will live, 1440 = 24 minutes (default session timeout).
     */
    const CACHE_TTL = 1440;

    protected $table = 'event_script';

    protected $fillable = [ 'name', 'type', 'content', 'config', 'is_active', 'affects_process' ];

    protected $casts = [ 'is_active' => 'boolean', 'affects_process' => 'boolean' ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function scriptType()
    {
        return $this->belongsTo( 'DreamFactory\Rave\Models\ScriptType', 'type', 'name' );
    }

    /**
     * Determine the handler for the script type
     *
     * @return string|null
     */
    protected function getScriptHandler()
    {
        if ( null !== $typeInfo = $this->scriptType()->first() )
        {
            // lookup related script type model
            return $typeInfo->class_name;
        }

        return null;
    }
}