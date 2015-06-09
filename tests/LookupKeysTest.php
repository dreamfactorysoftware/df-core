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
use DreamFactory\Rave\Models\Lookup;
use Illuminate\Support\Arr;
use DreamFactory\Rave\Utility\Session;
use DreamFactory\Rave\Models\App;

class LookupKeysTest extends \DreamFactory\Rave\Testing\TestCase
{
    protected $sytemLookup = [
        ['name'=>'host', 'value'=>'localhost'],
        ['name'=>'username', 'value'=>'jdoe'],
        ['name'=>'password', 'value'=>'1234', 'private'=>1]
    ];

    public function tearDown()
    {
        foreach($this->sytemLookup as $sl)
        {
            Lookup::whereName($sl['name'])->delete();
        }

        parent::tearDown();
    }

    public function testSystemLookup()
    {
        Lookup::create($this->sytemLookup[0]);

        $this->call(Verbs::GET, '/api/v2/system/environment');

        $this->assertEquals(null, Session::getWithApiKey('lookup.host'));
    }

    public function testSystemLookupWithApiKey()
    {
        $app = App::find(1);
        $apiKey = $app->api_key;

        Lookup::create($this->sytemLookup[0]);

        $this->call(Verbs::GET, '/api/v2/system/environment?api_key='.$apiKey);

        $this->assertEquals(Arr::get($this->sytemLookup, '0.value'), Session::getWithApiKey('lookup.host'));
    }
}