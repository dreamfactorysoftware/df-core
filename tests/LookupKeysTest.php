<?php

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Models\Lookup;
use Illuminate\Support\Arr;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Models\App;

class LookupKeysTest extends \DreamFactory\Core\Testing\TestCase
{
    protected $systemLookup = [
        ['name' => 'host', 'value' => 'localhost'],
        ['name' => 'username', 'value' => 'jdoe'],
        ['name' => 'password', 'value' => '1234', 'private' => true]
    ];

    public function tearDown(): void
    {
        foreach ($this->systemLookup as $sl) {
            Lookup::whereName($sl['name'])->delete();
        }

        parent::tearDown();
    }

    public function testSystemLookup()
    {
        Lookup::create($this->systemLookup[0]);

        $this->call(Verbs::GET, '/api/v2/system/environment');

        $this->assertEquals(Arr::get($this->systemLookup, '0.value'), Session::get('lookup.host'));
    }

    public function testSystemLookupWithApiKey()
    {
        $app = App::find(1);
        $apiKey = $app->api_key;

        Lookup::create($this->systemLookup[0]);

        $this->call(Verbs::GET, '/api/v2/system/environment?api_key=' . $apiKey);

        $this->assertEquals(Arr::get($this->systemLookup, '0.value'), Session::get('lookup.host'));
    }
}