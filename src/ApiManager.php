<?php

namespace Kevindierkx\Elicit;

use Kevindierkx\Elicit\ConnectionFactory;
use Kevindierkx\Elicit\ConnectionResolver;
use Kevindierkx\Elicit\ConnectionResolverInterface;
use Kevindierkx\Elicit\Elicit\Model;

class ApiManager
{
    /**
     * The connection factory instance.
     *
     * @var ConnectionFactory
     */
    protected $connectionFactory;

    /**
     * Create a new API manager instance.
     *
     * @param  ConnectionResolverInterface  $connectionResolver
     */
    public function __construct(ConnectionResolverInterface $connectionResolver = null)
    {
        if (is_null($connectionResolver)) {
            $connectionResolver = new ConnectionResolver;

            $connectionResolver->setDefaultConnection('default');
        }

        $this->setupConnectionFactory($connectionResolver);
    }

    /**
     * Build the connection factory instance.
     *
     * @param  ConnectionResolverInterface  $connectionResolver
     */
    protected function setupConnectionFactory(ConnectionResolverInterface $connectionResolver)
    {
        Model::setConnectionResolver($connectionResolver);

        $this->connectionFactory = new ConnectionFactory($connectionResolver);
    }

    /**
     * Register a connection with the connection factory.
     *
     * @param  array   $options
     * @param  string  $name
     * @return self
     */
    public function addConnection(array $options, $name = 'default')
    {
        $this->connectionFactory->addConnection($options, $name);

        return $this;
    }

    /**
     * Get a registered connection instance.
     *
     * @param  string  $name
     * @return ConnectionInterface
     */
    public function getConnection($name = null)
    {
        return $this->connectionFactory->getConnection($name);
    }
}
