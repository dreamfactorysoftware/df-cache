<?php

namespace DreamFactory\Core\Cache\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: distinct resource paths must produce distinct cache keys.
 *
 * Old behaviour: str_replace('/', '.', $path) made /user/1 and /user.1
 * collide; a tenant crafting `/user.1` could read or poison the cache
 * entry meant for `/user/1`.
 */
class CacheKeyCollisionTest extends TestCase
{
    /**
     * The class is abstract so we exercise the static helper directly
     * via reflection of any concrete subclass — but the helper is
     * static and public, so we can just bridge to it.
     */
    private function buildKey(string $path): string
    {
        return \DreamFactory\Core\Cache\Services\BaseService::buildCacheKey($path);
    }

    public function testDistinctInputsProduceDistinctKeys(): void
    {
        $a = $this->buildKey('/user/1');
        $b = $this->buildKey('/user.1');
        $this->assertNotSame($a, $b, 'cache keys for /user/1 and /user.1 must differ');
    }

    public function testDeeperPathsAlsoUnique(): void
    {
        $a = $this->buildKey('/tenants/42/users/7');
        $b = $this->buildKey('/tenants.42/users.7');
        $this->assertNotSame($a, $b);
    }
}
