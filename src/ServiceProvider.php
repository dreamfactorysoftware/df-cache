<?php
namespace DreamFactory\Core\Cache;

use DreamFactory\Core\Cache\Models\LocalConfig;
use DreamFactory\Core\Cache\Models\MemcachedConfig;
use DreamFactory\Core\Cache\Models\RedisConfig;
use DreamFactory\Core\Cache\Services\Local;
use DreamFactory\Core\Cache\Services\Memcached;
use DreamFactory\Core\Cache\Services\Redis;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;


class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType([
                    'name'           => 'cache_local',
                    'label'          => 'Local Cache',
                    'description'    => 'Local Cache service.',
                    'group'          => ServiceTypeGroups::CACHE,
                    'config_handler' => LocalConfig::class,
                    'factory'        => function ($config){
                        return new Local($config);
                    },
                ])
            );
            $df->addType(
                new ServiceType([
                    'name'           => 'cache_memcached',
                    'label'          => 'Memcached',
                    'description'    => 'Memcached Cache service.',
                    'group'          => ServiceTypeGroups::CACHE,
                    'config_handler' => MemcachedConfig::class,
                    'factory'        => function ($config){
                        return new Memcached($config);
                    },
                ])
            );
            $df->addType(
                new ServiceType([
                    'name'           => 'cache_redis',
                    'label'          => 'Redis',
                    'description'    => 'Redis Cache service.',
                    'group'          => ServiceTypeGroups::CACHE,
                    'config_handler' => RedisConfig::class,
                    'factory'        => function ($config){
                        return new Redis($config);
                    },
                ])
            );
        });
    }
}