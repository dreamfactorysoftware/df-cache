<?php
namespace DreamFactory\Core\Cache\Services;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\ResponseFactory;
use Illuminate\Cache\Repository;

abstract class BaseService extends \DreamFactory\Core\Services\BaseRestService
{
    /** @var Repository */
    protected $store = null;

    /** @var integer|null */
    protected $ttl = null;

    /**
     * BaseService constructor.
     *
     * @param array $settings
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function __construct(array $settings)
    {
        parent::__construct($settings);

        $config = array_get($settings, 'config');

        if (empty($config)) {
            throw new InternalServerErrorException('No service configuration found for Redis Cache.');
        }

        $this->ttl = array_get($config, 'default_ttl', \Config::get('df.default_cache_ttl', 300));
        $this->setStore($config);
    }

    /**
     * Sets the cache store
     *
     * @param $config
     *
     * @return mixed
     */
    abstract protected function setStore($config);

    /** {@inheritdoc} */
    protected function handleGET()
    {
        $key = str_replace('/', '.', $this->resourcePath);
        if (empty($key)) {
            throw new BadRequestException('No key/resource provide. Please provide a cache key to retrieve.');
        }

        $default = $this->request->getParameter('default');
        if (false === $this->store->has($key) && empty($default)) {
            throw new NotFoundException('No value found for key [' . $key . ']');
        }

        $pull = $this->request->getParameterAsBool('clear', $this->request->getParameterAsBool('pull'));
        if ($pull) {
            $result = $this->store->pull($key, $default);
        } else {
            $result = $this->store->get($key, $default);
        }

        return $result;
    }

    /** {@inheritdoc} */
    protected function handlePUT()
    {
        $key = str_replace('/', '.', $this->resourcePath);
        $payload = $this->getPayloadData();
        $this->ttl = $this->request->getParameter('ttl', $this->ttl);
        $forever = $this->request->getParameterAsBool('forever');

        if (empty($payload)) {
            throw new BadRequestException('No value/payload provided to store in cache.');
        }

        if (!empty($key)) {
            if ($forever) {
                $this->store->forever($key, $payload);
            } else {
                $this->store->put($key, $payload, $this->ttl);
            }

            return [$key => $payload];
        } else {
            if (!is_array($payload)) {
                throw new BadRequestException('Invalid payload provided. Please provide a key/value pair.');
            }

            foreach ($payload as $k => $v) {
                if ($forever) {
                    $this->store->forever($k, $v);
                } else {
                    $this->store->put($k, $v, $this->ttl);
                }
            }

            return ['success' => true];
        }
    }

    /** {@inheritdoc} */
    protected function handlePOST()
    {
        $key = str_replace('/', '.', $this->resourcePath);
        $payload = $this->getPayloadData();

        if (!empty($key) && true === $this->store->has($key)) {
            throw new BadRequestException(
                'Key [' . $key . '] already exists in cache. Use PUT to update existing key.'
            );
        }

        if (is_array($payload) && empty($key)) {
            $badKeys = [];
            foreach ($payload as $k => $v) {
                if (true === $this->store->has($k)) {
                    $badKeys[] = $k;
                }
            }

            if (!empty($badKeys)) {
                throw new BadRequestException(
                    'Nothing was stored in cache. ' .
                    'One or more key(s) already exists (' . implode(',', $badKeys) . ')'
                );
            }
        }

        $result = $this->handlePUT();

        return ResponseFactory::create($result, null, ServiceResponseInterface::HTTP_CREATED);
    }

    /** {@inheritdoc} */
    protected function handleDELETE()
    {
        $key = str_replace('/', '.', $this->resourcePath);
        if (empty($key)) {
            throw new BadRequestException('No key/resource provide. Please provide a cache key to delete.');
        }

        $result = $this->store->forget($key);

        return ['success' => $result];
    }

    /** {@inheritdoc} */
    protected function handlePATCH()
    {
        return $this->handlePUT();
    }

    /** {@inheritdoc} */
    protected function getPayloadData($key = null, $default = null)
    {
        $content = $this->request->getContent();
        $contentType = $this->request->getContentType();

        switch ($contentType) {
            case 'txt':
                return $content;
            case 'json':
                if (!in_array(substr($content, 0, 1), ['{', '[']) &&
                    !in_array(substr($content, strlen($content) - 1), ['}', ']'])
                ) {
                    return $content;
                } else {
                    return $this->request->getPayloadData();
                }
                break;
            default:
                return $this->request->getPayloadData();
        }
    }
}