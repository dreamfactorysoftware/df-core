<?php

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Models\Lookup;
use Illuminate\Support\Arr;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Models\App;

class LookupKeysTest extends \DreamFactory\Core\Testing\TestCase
{
    protected $sytemLookup = [
        ['name' => 'host', 'value' => 'localhost'],
        ['name' => 'username', 'value' => 'jdoe'],
        ['name' => 'password', 'value' => '1234', 'private' => 1]
    ];

    public function tearDown()
    {
        foreach ($this->sytemLookup as $sl) {
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

        $this->call(Verbs::GET, '/api/v2/system/environment?api_key=' . $apiKey);

        $this->assertEquals(Arr::get($this->sytemLookup, '0.value'), Session::getWithApiKey('lookup.host'));
    }
}