<?php

use DreamFactory\Core\Components\Cacheable;

class CacheableFixture
{
    use Cacheable;

    public function __construct($prefix)
    {
        $this->cachePrefix = $prefix;
        $this->cacheTTL = 300;
    }
}

class CacheableRaceFixture extends CacheableFixture
{
    public $onKey;

    protected function makeCacheKey($name)
    {
        if ($this->onKey && $name === 'k2') {
            $fn = $this->onKey;
            $this->onKey = null;
            $fn();
        }

        return parent::makeCacheKey($name);
    }
}

class CacheableTest extends \DreamFactory\Core\Testing\TestCase
{
    protected function prefix()
    {
        return 'cacheable_test_' . uniqid() . ':';
    }

    public function testFlushForgetsKeysAddedByAnotherInstance()
    {
        $p = $this->prefix();
        $a = new CacheableFixture($p);
        $b = new CacheableFixture($p);
        $a->addToCache('a', 1, true);
        $b->addToCache('b', 2, true);
        $a->addToCache('c', 3, true);

        (new CacheableFixture($p))->flush();

        $this->assertFalse(Cache::has($p . 'a'));
        $this->assertFalse(Cache::has($p . 'b'));
        $this->assertFalse(Cache::has($p . 'c'));
    }

    public function testRemoveFromCacheKeepsOtherInstancesKeys()
    {
        $p = $this->prefix();
        $a = new CacheableFixture($p);
        $b = new CacheableFixture($p);
        $a->addToCache('a', 1);
        $b->addToCache('b', 2);
        $a->removeFromCache('a');

        (new CacheableFixture($p))->flush();

        $this->assertFalse(Cache::has($p . 'b'));
    }

    public function testRememberCacheHitDoesNotDropIndex()
    {
        $p = $this->prefix();
        $a = new CacheableFixture($p);
        $b = new CacheableFixture($p);
        $a->rememberCache('x', function () {
            return 1;
        });
        $a->rememberCache('x', function () {
            return 1;
        });
        $b->addToCache('y', 2);
        $a->rememberCache('x', function () {
            return 1;
        });

        (new CacheableFixture($p))->flush();

        $this->assertFalse(Cache::has($p . 'x'));
        $this->assertFalse(Cache::has($p . 'y'));
    }

    public function testReCacheDuringFlushIsStillFlushable()
    {
        $p = $this->prefix();
        $setup = new CacheableFixture($p);
        $setup->addToCache('k1', 1);
        $setup->addToCache('k2', 2);

        // another worker re-caches k1 after flush() has forgotten it
        $flusher = new CacheableRaceFixture($p);
        $flusher->onKey = function () use ($p) {
            (new CacheableFixture($p))->addToCache('k1', 'new');
        };
        $flusher->flush();

        (new CacheableFixture($p))->flush();

        $this->assertFalse(Cache::has($p . 'k1'));
        $this->assertFalse(Cache::has($p . 'k2'));
    }
}
