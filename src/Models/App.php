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

use DreamFactory\Rave\Enums\AppTypes;

/**
 * App
 *
 * @property integer $id
 * @property string  $name
 * @property string  $api_key
 * @property string  $description
 * @property boolean $is_active
 * @property integer $role_id
 * @property integer $type
 * @property integer $storage_service_id
 * @property string  $storage_component
 * @property string  $path
 * @property string  $url
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|App whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|App whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|App whereApiKey( $value )
 * @method static \Illuminate\Database\Query\Builder|App whereIsActive( $value )
 * @method static \Illuminate\Database\Query\Builder|App whereRoleId( $value )
 * @method static \Illuminate\Database\Query\Builder|App whereStorageServiceId( $value )
 * @method static \Illuminate\Database\Query\Builder|App whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|App whereLastModifiedDate( $value )
 */
class App extends BaseSystemModel
{
    protected $table = 'app';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'type',
        'path',
        'url',
        'storage_service_id',
        'storage_container',
        'requires_fullscreen',
        'allow_fullscreen_toggle',
        'toggle_location',
        'role_id'
    ];

    protected $appends = [ 'launch_url' ];

    protected $casts = [
        'is_active'               => 'boolean',
        'requires_fullscreen'     => 'boolean',
        'allow_fullscreen_toggle' => 'boolean'
    ];

    public static function generateApiKey( $name )
    {
        $string = gethostname() . $name . time();
        $key = hash( 'sha256', $string );

        return $key;
    }

    /**
     * @param       $record
     * @param array $params
     *
     * @return array
     */
    protected static function createInternal( $record, $params = [ ] )
    {
        try
        {
            /** @var App $model */
            $model = static::create( $record );
            $apiKey = static::generateApiKey( $model->name );
            $model->api_key = $apiKey;
            $model->save();
        }
        catch ( \PDOException $e )
        {
            throw $e;
        }

        return static::buildResult( $model, $params );
    }

    public function getLaunchUrlAttribute()
    {
        $launchUrl = '';
        switch ( $this->type )
        {
            case AppTypes::STORAGE_SERVICE:
                if ( !empty( $this->storage_service_id ) )
                {
                    /** @var $_service Service */
                    $_service = Service::whereId( $this->storage_service_id )->first();
                    if ( !empty( $_service ) )
                    {
                        $launchUrl .= $_service->name . '/';
                        if ( !empty( $this->storage_container ) )
                        {
                            $launchUrl .= $this->storage_container . '/';
                        }
                        $launchUrl .= $this->name . '/' . ltrim( $this->path, '/' );
                        $launchUrl = url( $launchUrl );
                    }
                }
                break;

            case AppTypes::PATH:
                $launchUrl = url( $this->path );
                break;

            case AppTypes::URL:
                $launchUrl = $this->url;
                break;
        }

        return $launchUrl;
    }
}