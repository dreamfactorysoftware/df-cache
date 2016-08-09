<?php
namespace DreamFactory\Core\Cache\Services;

use Illuminate\Cache\MemcachedConnector;
use Illuminate\Cache\MemcachedStore;
use DreamFactory\Core\Utility\Session;

class Memcached extends BaseService
{
    /** Default Memcached server port */
    const PORT = 11211;

    protected function setStore($config)
    {
        Session::replaceLookups($config, true);
        $host = array_get($config, 'host');
        $port = array_get($config, 'port', static::PORT);
        $options = array_get($config, 'options');

        $servers = [
            [
                'host' => $host,
                'port' => $port,
                'weight' => 100
            ]
        ];

        if (!empty($options)) {
            $servers[0] = array_merge($options, $servers[0]);
        }

        $connector = new MemcachedConnector();
        $memcached = $connector->connect($servers);
        $memcachedStore = new MemcachedStore($memcached);
        $this->store = \Cache::repository($memcachedStore);
    }
}