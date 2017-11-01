<?php

namespace DreamFactory\Core\Cache\Services;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResponseFactory;
use Illuminate\Cache\Repository;

abstract class BaseService extends BaseRestService
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
            throw new NotFoundException("No value found for key '$key'.");
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
            throw new BadRequestException("Key '$key' already exists in cache. Use PUT to update existing key.");
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

    /** {@inheritdoc} */
    protected function getApiDocPaths()
    {
        $capitalized = camelize($this->name);

        $base = [
            '/'           => [
                'post' => [
                    'summary'     => 'create' . $capitalized . 'Keys() - Create one or more keys in cache storage',
                    'operationId' => 'create' . $capitalized . 'Keys',
                    'parameters'  => [
                        [
                            'name'        => 'ttl',
                            'schema'      => ['type' => 'integer'],
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                        ],
                        [
                            'name'        => 'forever',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' => 'Setting this to true will never expire your key/value pair from cache storage.',
                        ],
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/CacheRequest'
                    ],
                    'responses'   => [
                        '201' => ['$ref' => '#/components/responses/Success']
                    ],
                    'description' => 'No keys will be created if any of the supplied keys exist in cache. ' .
                        'Use PUT to replace an existing key(s). ' .
                        'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'put'  => [
                    'summary'     => 'replace' . $capitalized . 'Keys() - Replace one or more keys in cache storage',
                    'operationId' => 'replace' . $capitalized . 'Keys',
                    'parameters'  => [
                        [
                            'name'        => 'ttl',
                            'schema'      => ['type' => 'integer'],
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                        ],
                        [
                            'name'        => 'forever',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' => 'Setting this to true will never expire your key/value pair from cache storage.',
                        ],
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/CacheRequest'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/Success']
                    ],
                    'description' => 'All existing keys will be replaced by supplied keys. ' .
                        'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ]
            ],
            '/{key_name}' => [
                'parameters' => [
                    [
                        'name'        => 'key_name',
                        'description' => 'The name of the key you want to retrieve from cache storage',
                        'schema'      => ['type' => 'string'],
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'summary'     => 'get' . $capitalized . 'Key() - Retrieve one key from cache.',
                    'operationId' => 'get' . $capitalized . 'Key',
                    'parameters'  => [
                        [
                            'name'        => 'default',
                            'schema'      => ['type' => 'string'],
                            'in'          => 'query',
                            'description' => 'A default value to return if key is not found in cache storage.',
                        ],
                        [
                            'name'        => 'clear',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will delete the key/value pair from cache storage upon reading it.',
                        ]
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/CacheKeyResponse'],
                    ],
                    'description' => 'Use the \'clear\' parameter to retrieve and forget a key from cache. ' .
                        'Use the \'default\' parameter to return a default value if key is not found.'
                ],
                'post'       => [
                    'summary'     => 'create' . $capitalized . 'Key() - Create one key in cache.',
                    'operationId' => 'create' . $capitalized . 'Key',
                    'parameters'  => [
                        [
                            'name'        => 'ttl',
                            'schema'      => ['type' => 'integer'],
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                        ],
                        [
                            'name'        => 'forever',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will never expire your key/value pair from cache storage.',
                        ],
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/CacheKeyRequest'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/CacheKeyResponse'],
                    ],
                    'description' => 'You can only create a key if the key does not exist in cache. ' .
                        'Use PUT to replace an existing key. ' .
                        'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'put'        => [
                    'summary'     => 'replace' . $capitalized . 'Key() - Replace one key from cache.',
                    'operationId' => 'replace' . $capitalized . 'Key',
                    'parameters'  => [
                        [
                            'name'        => 'ttl',
                            'schema'      => ['type' => 'integer'],
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                        ],
                        [
                            'name'        => 'forever',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will never expire your key/value pair from cache storage.',
                        ],
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/CacheKeyRequest'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/CacheKeyResponse'],
                    ],
                    'description' => 'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'patch'      => [
                    'summary'     => 'update' . $capitalized . 'Key() - Update one key in cache.',
                    'operationId' => 'update' . $capitalized . 'Key',
                    'parameters'  => [
                        [
                            'name'        => 'ttl',
                            'schema'      => ['type' => 'integer'],
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                        ],
                        [
                            'name'        => 'forever',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' => 'Setting this to true will never expire your key/value pair from cache storage.',
                        ],
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/CacheKeyRequest'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/CacheKeyResponse'],
                    ],
                    'description' => 'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'delete'     => [
                    'summary'     => 'delete' . $capitalized . 'Key() - Delete one key from cache.',
                    'operationId' => 'delete' . $capitalized . 'Key',
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/Success']
                    ],
                    'description' => 'You can only delete one key at a time.'
                ],
            ],
        ];

        return $base;
    }

    protected function getApiDocRequests()
    {
        return [
            'CacheRequest'    => [
                'description' => 'Content - key/value pair.',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/KeyValueObject']
                    ],
                    'application/xml' => [
                        'schema' => ['$ref' => '#/components/schemas/KeyValueObject']
                    ],
                ],
            ],
            'CacheKeyRequest' => [
                'description' => 'Content - plain text or json string',
                'content'     => [
                    'application/json' => [
                        'schema' => ['type' => 'string'],
                    ],
                    'application/xml' => [
                        'schema' => ['type' => 'string'],
                    ],
                ],
            ]
        ];
    }

    protected function getApiDocResponses()
    {
        return [
            'CacheResponse'    => [
                'description' => 'Content - key/value pair.',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/KeyValueObject']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/KeyValueObject']
                    ],
                ],
            ],
            'CacheKeyResponse' => [
                'description' => 'Content - plain text or json string',
                'content'     => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'string'
                        ],
                    ],
                    'application/xml'  => [
                        'schema' => [
                            'type' => 'string'
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getApiDocSchemas()
    {
        return [
            'KeyValueObject' => [
                'type'       => 'object',
                'properties' => [
                    '{key_name}' => [
                        'type'        => 'string',
                        'description' => 'Value for your key goes here. You should replace {key_name} with your key.'
                    ]
                ]
            ],
        ];
    }
}