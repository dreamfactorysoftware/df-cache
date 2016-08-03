<?php
namespace DreamFactory\Core\Cache\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

class RedisConfig extends BaseServiceConfigModel
{
    protected $table = 'redis_config';

    protected $fillable = ['service_id', 'host', 'port', 'password', 'database_index', 'options', 'default_ttl'];

    protected $casts = [
        'service_id'     => 'integer',
        'database_index' => 'integer',
        'options'        => 'array',
        'default_ttl'    => 'integer'
    ];

    protected $encrypted = ['password'];

    /** {@inheritdoc} */
    public function fromJson($value, $asObject = false)
    {
        $value = json_decode($value, !$asObject);

        if (is_array($value) && isset($value['ssl'])) {
            if (!is_array($value['ssl'])) {
                $value['ssl'] = json_decode($value['ssl'], true);
            }
        }

        return $value;
    }

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
                $schema['description'] = 'IP Address/Hostname of your Redis server.';
                break;
            case 'port':
                $schema['label'] = 'Port';
                $schema['default'] = 6379;
                $schema['description'] = 'Redis Port number';
                break;
            case 'password':
                $schema['label'] = 'Password';
                $schema['description'] = 'Redis Password';
                break;
            case 'options':
                $schema['type'] = 'object';
                $schema['object'] =
                    [
                        'key'   => ['label' => 'Name', 'type' => 'string'],
                        'value' => ['label' => 'Value', 'type' => 'string']
                    ];
                $schema['description'] =
                    'An array of options for the Redis connection.' .
                    ' For further details, see https://github.com/nrk/predis/wiki/Quick-tour#connection';
                break;
            case 'default_ttl':
                $schema['label'] = 'Default TTL';
                $schema['default'] = 300;
                $schema['description'] = 'Time To Live - Time in minutes before the cached value expires';
        }
    }
}