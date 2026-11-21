<?php

use DreamFactory\Core\Models\ServiceCacheConfig;
use DreamFactory\Core\Facades\ServiceManager;
use Illuminate\Support\Facades\DB;

class ServiceCacheConfigTest extends \DreamFactory\Core\Testing\TestCase
{
    public function testNullCacheEnabledIsStoredAsFalse()
    {
        $m = new ServiceCacheConfig();
        $m->fill(['cache_enabled' => null, 'cache_ttl' => 0]);

        $this->assertSame(false, $m->getAttributes()['cache_enabled']);
    }

    public function testSetConfigWithNullCacheEnabledSaves()
    {
        $id = ServiceManager::getServiceIdByName('db');
        $existing = DB::table('service_cache_config')->where('service_id', $id)->first();
        DB::table('service_cache_config')->where('service_id', $id)->delete();

        try {
            ServiceCacheConfig::setConfig($id, ['cache_enabled' => null, 'cache_ttl' => 0]);

            $row = DB::table('service_cache_config')->where('service_id', $id)->first();
            $this->assertEquals(0, $row->cache_enabled);
            $this->assertSame(false, ServiceCacheConfig::whereServiceId($id)->first()->cache_enabled);
        } finally {
            DB::table('service_cache_config')->where('service_id', $id)->delete();
            if ($existing) {
                DB::table('service_cache_config')->insert((array)$existing);
            }
        }
    }

    /**
     * @dataProvider booleanProvider
     */
    public function testStringBooleansAreCoerced($value, $expected)
    {
        $m = new ServiceCacheConfig();
        $m->fill(['cache_enabled' => $value]);

        $this->assertSame($expected, $m->getAttributes()['cache_enabled']);
    }

    public static function booleanProvider()
    {
        return [
            [true, true],
            [false, false],
            ['1', true],
            ['0', false],
            ['true', true],
            ['false', false],
            [1, true],
            [0, false],
        ];
    }
}
