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

use DreamFactory\Library\Utility\Enums\Verbs;
use Illuminate\Support\Arr;
use DreamFactory\Library\Utility\Scalar;

class AdminResourceTest extends \DreamFactory\Rave\Testing\UserResourceTestCase
{
    const RESOURCE = 'admin';

    protected function adminCheck( $records )
    {
        foreach ( $records as $user )
        {
            $userModel = \DreamFactory\Rave\Models\User::find( $user['id'] );

            if ( !Scalar::boolval( $userModel->is_sys_admin ) )
            {
                return false;
            }
        }

        return true;
    }

    public function testNonAdmin()
    {
        $user = $this->user1;
        $payload = json_encode( [ $user ], JSON_UNESCAPED_SLASHES );
        $this->makeRequest( Verbs::POST, 'user', [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], $payload );

        //Using a new instance here. Prev instance is set for user resource.
        $this->service = \DreamFactory\Rave\Utility\ServiceHandler::getService('system');

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE);
        $content = $rs->getContent();

        $this->assertEquals(1, count($content['record']));
        $this->assertEquals('Rave Admin', Arr::get($content, 'record.0.name'));
    }
}