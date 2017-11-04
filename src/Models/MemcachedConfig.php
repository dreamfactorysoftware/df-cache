<?php

namespace DreamFactory\Core\Cache\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

class MemcachedConfig extends BaseServiceConfigModel
{
    protected $table = 'memcached_config';

    protected $fillable = ['service_id', 'host', 'port', 'options', 'default_ttl'];

    protected $casts = [
        'service_id'  => 'integer',
        'port'        => 'integer',
        'options'     => 'array',
        'default_ttl' => 'integer'
    ];

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'host':
                $schema['label'] = 'Host';
                $schema['default'] = '127.0.0.1';
                $schema['description'] = 'IP Address/Hostname of your Memcached server.';
                break;
            case 'port':
                $schema['label'] = 'Port';
                $schema['default'] = 11211;
                $schema['description'] = 'Memcached Port number';
                break;
            case 'default_ttl':
                $schema['label'] = 'Default TTL';
                $schema['default'] = 300;
                $schema['description'] = 'Time To Live - Time in minutes before the cached value expires';
        }
    }
}