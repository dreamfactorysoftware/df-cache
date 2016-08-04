<?php
namespace DreamFactory\Core\Cache\Services;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\ResponseFactory;
use Illuminate\Cache\Repository;
use DreamFactory\Library\Utility\Inflector;

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

    /** {@inheritdoc} */
    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();
        $name = strtolower($this->name);
        $capitalized = Inflector::camelize($this->name);

        $base['paths'] = [
            '/' . $name => [
                'post' => [
                    'tags' => [$name],
                    'summary' => 'create' . $capitalized . 'Keys() - Create one or more keys in cache storage',
                    'operationId' => 'create' . $capitalized . 'Keys',
                    'x-publishedEvents' => [
                        $name . '.create'
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'parameters'        => [
                        [
                            'name'        => 'ttl',
                            'type'        => 'integer',
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                            'required'    => false
                        ],
                        [
                            'name'        => 'forever',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will never expire your key/value pair from cache storage.',
                            'required'    => false,
                            'default'     => false
                        ]
                    ],
                    'responses'         => [
                        '201'     => [
                            'description' => 'Success',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'success' => [
                                        'type' => 'boolean'
                                    ]
                                ]
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'No keys will be created if any of the supplied keys exist in cache. ' .
                        'Use PUT to replace an existing key(s). ' .
                        'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'put' => [
                    'tags' => [$name],
                    'summary' => 'replace' . $capitalized . 'Keys() - Replace one or more keys in cache storage',
                    'operationId' => 'replace' . $capitalized . 'Keys',
                    'x-publishedEvents' => [
                        $name . '.update'
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'parameters'        => [
                        [
                            'name'        => 'ttl',
                            'type'        => 'integer',
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                            'required'    => false
                        ],
                        [
                            'name'        => 'forever',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will never expire your key/value pair from cache storage.',
                            'required'    => false,
                            'default'     => false
                        ]
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'success' => [
                                        'type' => 'boolean'
                                    ]
                                ]
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'All existing keys will be replaced by supplied keys. ' .
                        'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ]
            ],
            '/' . $name . '/{key_name}' => [
                'parameters' => [
                    [
                        'name'        => 'key_name',
                        'description' => 'The name of the key you want to retrieve from cache storage',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'              => [$name],
                    'summary'           => 'get' . $capitalized . 'Key() - Retrieve one key from cache.',
                    'operationId'       => 'get' . $capitalized . 'Key',
                    'x-publishedEvents' => [
                        $name . '.{key_name}.read'
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'parameters'        => [
                        [
                            'name'        => 'default',
                            'type'        => 'string',
                            'in'          => 'query',
                            'description' => 'A default value to return if key is not found in cache storage.',
                            'required'    => false
                        ],
                        [
                            'name'        => 'clear',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will delete the key/value pair from cache storage upon reading it.',
                            'required'    => false,
                            'default'     => false
                        ]
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['type' => 'object']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'Use the \'clear\' parameter to retrieve and forget a key from cache. ' .
                        'Use the \'default\' parameter to return a default value if key is not found.'
                ],
                'post'       => [
                    'tags'              => [$name],
                    'summary'           => 'create' . $capitalized . 'Key() - Create one key in cache.',
                    'operationId'       => 'create' . $capitalized . 'Key',
                    'x-publishedEvents' => [
                        $name . '.{key_name}.create'
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'parameters'        => [
                        [
                            'name'        => 'ttl',
                            'type'        => 'integer',
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                            'required'    => false
                        ],
                        [
                            'name'        => 'forever',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will never expire your key/value pair from cache storage.',
                            'required'    => false,
                            'default'     => false
                        ]
                    ],
                    'responses'         => [
                        '201'     => [
                            'description' => 'Success',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    '{key_name}' => [
                                        'type' => 'object'
                                    ]
                                ]
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'You can only create a key if the key does not exist in cache. ' .
                        'Use PUT to replace an existing key. ' .
                        'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'put'        => [
                    'tags'              => [$name],
                    'summary'           => 'replace' . $capitalized . 'Key() - Replace one key from cache.',
                    'operationId'       => 'replace' . $capitalized . 'Key',
                    'x-publishedEvents' => [
                        $name . '.{key_name}.update'
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'parameters'        => [
                        [
                            'name'        => 'ttl',
                            'type'        => 'integer',
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                            'required'    => false
                        ],
                        [
                            'name'        => 'forever',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will never expire your key/value pair from cache storage.',
                            'required'    => false,
                            'default'     => false
                        ]
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    '{key_name}' => [
                                        'type' => 'object'
                                    ]
                                ]
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'patch'      => [
                    'tags'              => [$name],
                    'summary'           => 'update' . $capitalized . 'Key() - Update one key in cache.',
                    'operationId'       => 'update' . $capitalized . 'Key',
                    'x-publishedEvents' => [
                        $name . '.{key_name}.update'
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'parameters'        => [
                        [
                            'name'        => 'ttl',
                            'type'        => 'integer',
                            'in'          => 'query',
                            'description' => 'A TTL (Time To Live) value in minutes for your cache.',
                            'required'    => false
                        ],
                        [
                            'name'        => 'forever',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'description' =>
                                'Setting this to true will never expire your key/value pair from cache storage.',
                            'required'    => false,
                            'default'     => false
                        ]
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    '{key_name}' => [
                                        'type' => 'object'
                                    ]
                                ]
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'Use the \'ttl\' parameter to set a Time to Live value in minutes. ' .
                        'Use the \'forever\' parameter to store a key/value pair for indefinite time.'
                ],
                'delete'     => [
                    'tags'              => [$name],
                    'summary'           => 'delete' . $capitalized . 'Key() - Delete one key from cache.',
                    'operationId'       => 'delete' . $capitalized . 'Key',
                    'x-publishedEvents' => [
                        $name . '.{key_name}.delete'
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv', 'text/plain'],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                'type'       => 'object',
                                'properties' => [
                                    'success' => [
                                        'type' => 'boolean'
                                    ]
                                ]
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'You can only delete one key at a time.'
                ],
            ],
        ];

        return $base;
    }
}