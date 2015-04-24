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
 * ScriptConfig
 *
 * @property integer    $service_id
 * @property string     $type
 * @property string     $content
 * @property string     $config
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereServiceId( $value )
 * @method static \Illuminate\Database\Query\Builder|ScriptConfig whereLanguage( $value )
 */
class ScriptConfig extends BaseServiceConfigModel
{
    /**
     * @const string The private cache file
     */
    const CACHE_PREFIX = 'script/';
    /**
     * @const integer How long a ScriptConfig cache will live, 1440 = 24 minutes (default session timeout).
     */
    const CACHE_TTL = 1440;

    protected $table = 'script_config';

    protected $fillable = [ 'service_id', 'type', 'engine', 'content', 'config' ];

    protected $appends = [ 'engine' ];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $engine = [ ];

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

    /**
     * @return mixed
     */
    public function getEngineAttribute()
    {
        $engine = $this->scriptType()->first();
        if ( !empty( $engine ) )
        {
            $this->engine = $engine->toArray();
        }

        return $this->engine;
    }
}