<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Rave\Utility;

/**
 * ApiDocUtilities
 */
class ApiDocUtilities
{
    /**
     * Returns an array of common responses for merging into Swagger files.
     *
     * @param array $codes Array of response codes to return only. If empty, all are returned.
     *
     * @return array
     */
    public static function getCommonResponses( array $codes = [ ] )
    {
        static $_commonResponses = [
            [
                'code'    => 400,
                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
            ],
            [
                'code'    => 401,
                'message' => 'Unauthorized Access - No currently valid authorization has been made.',
            ],
            [
                'code'    => 403,
                'message' => 'Forbidden Access - Access to this service or resource is forbidden with the given authorization.',
            ],
            [
                'code'    => 404,
                'message' => 'Not Found - Service or resource was not found',
            ],
            [
                'code'    => 500,
                'message' => 'System Error - Specific reason is included in the error message',
            ],
        ];

        $_response = $_commonResponses;

        if ( !empty( $codes ) )
        {
            foreach ( $codes as $_code )
            {
                foreach ( $_commonResponses as $_commonResponse )
                {
                    if ( !isset( $_commonResponse['code'] ) || $_code != $_commonResponse['code'] )
                    {
                        unset( $_response[$_commonResponse['code']] );
                    }
                }
            }
        }

        return $_response;
    }

    /**
     * Returns a common set of properties for all system resources
     *
     * @return array
     */
    public static function getTimestampProperties()
    {
        return [
            'created_date'       => [
                'type'        => 'string',
                'description' => 'The date the resource was created.',
                'readOnly'    => true,
            ],
            'last_modified_date' => [
                'type'        => 'string',
                'description' => 'The date the resource was last modified.',
                'readOnly'    => true,
            ],
        ];
    }
}
