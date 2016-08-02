<?php
namespace DreamFactory\Core\Cache\Services;

use DreamFactory\Core\Exceptions\InternalServerErrorException;

class Local extends BaseService
{
    protected function setStore($config)
    {
        $store = array_get($config, 'store');
        $availableStores = array_keys(\Config::get('cache.stores', []));

        if (!in_array($store, $availableStores)) {
            throw new InternalServerErrorException('Invalid cache store provided  [' . $store . ']');
        }

        $this->store = \Cache::store($store);
    }
}