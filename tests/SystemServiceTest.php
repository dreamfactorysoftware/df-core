<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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

class SystemServiceTest extends \DreamFactory\Core\Testing\TestCase
{
    const RESOURCE = 'service';

    protected $serviceId = 'system';

    /************************************************
     * Testing GET
     ************************************************/

    public function testGETService()
    {
        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE);
        $content = $rs->getContent();
        $services = Arr::get($content, 'record');

        $first4 = Arr::get($services, '0.name');
        $first4 .= ','.Arr::get($services, '1.name');
        $first4 .= ','.Arr::get($services, '2.name');
        $first4 .= ','.Arr::get($services, '3.name');

        $this->assertEquals('system,api_docs,event,user', $first4);
    }

    public function testGETServiceById()
    {
        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE.'/1');
        $content = $rs->getContent();

        $this->assertEquals('system', Arr::get($content, 'name'));
        $this->assertEquals(13, count($content));
    }

    public function testGETServiceByIdWithFields()
    {
        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE.'/1', ['fields'=>'name,label,id']);
        $content = $rs->getContent();

        $this->assertEquals('system', Arr::get($content, 'name'));
        $this->assertEquals('System Management', Arr::get($content, 'label'));
        $this->assertEquals(1, Arr::get($content, 'id'));
        $this->assertEquals(3, count($content));
    }
}