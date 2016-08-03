<?php
namespace DreamFactory\Core\Cache\Services;

use Illuminate\Redis\Database;
use Illuminate\Cache\RedisStore;

class Redis extends BaseService
{
    /** Default Redis server port */
    const PORT = 6379;

    /** {@inheritdoc} */
    protected function setStore($config)
    {
        $host = array_get($config, 'host');
        $port = array_get($config, 'port', static::PORT);
        $databaseIndex = array_get($config, 'database_index', 0);
        $password = array_get($config, 'password');
        $options = array_get($config, 'options');

        $server = [
            'cluster' => false,
            'default' => [
                'host'     => $host,
                'port'     => $port,
                'database' => $databaseIndex,
                'password' => $password
            ]
        ];

        if (!empty($options)) {
            $server['default'] = array_merge($options, $server['default']);
        }

        $redisDatabase = new Database($server);
        $redisStore = new RedisStore($redisDatabase);
        $this->store = \Cache::repository($redisStore);
    }
}