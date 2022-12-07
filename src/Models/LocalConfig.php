<?php
namespace DreamFactory\Core\Cache\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

class LocalConfig extends BaseServiceConfigModel
{
    protected $table = 'local_cache_config';

    protected $fillable = ['service_id', 'store', 'default_ttl'];

    protected $casts = [
        'service_id'  => 'integer',
        'default_ttl' => 'integer'
    ];

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'store':
                $values = [];
                $defaultStore = \Config::get('cache.default');
                $stores = \Config::get('cache.stores');

                foreach ($stores as $key => $disk) {
                    if (str_starts_with($key, 'dfe-')) {
                        continue;
                    }
                    $default = false;
                    if ($defaultStore === $key) {
                        $default = true;
                    }
                    $values[] = [
                        'name'    => $key,
                        'label'   => $key,
                        'default' => $default
                    ];
                }

                $schema['default'] = $defaultStore;
                $schema['type'] = 'picklist';
                $schema['description'] = 'Select a store to use for local cache service.';
                $schema['values'] = $values;
                break;
            case 'default_ttl':
                $schema['label'] = 'Default TTL';
                $schema['default'] = 300;
                $schema['description'] = 'Time To Live - Time in minutes before the cached value expires';
        }
    }
}