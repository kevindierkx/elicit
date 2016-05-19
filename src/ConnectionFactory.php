<?php

namespace Kevindierkx\Elicit;

use InvalidArgumentException;
use Kevindierkx\Elicit\Connection\AbstractConnection;
use Kevindierkx\Elicit\Connection\GenericConnection;
use Kevindierkx\Elicit\ConnectionResolver;
use Kevindierkx\Elicit\ConnectionResolverInterface;
use Kevindierkx\Elicit\Connector\AbstractConnector;
use Kevindierkx\Elicit\Connector\GenericConnector;

class ConnectionFactory
{
    /**
     * @var ConnectionResolverInterface
     */
    protected $connectionResolver;

    /**
     * Create a new connection factory instance.
     *
     * @param  ConnectionResolverInterface  $connectionResolver
     */
    public function __construct(ConnectionResolverInterface $connectionResolver = null)
    {
        if (is_null($connectionResolver)) {
            $connectionResolver = new ConnectionResolver;
        }

        $this->connectionResolver = $connectionResolver;
    }

    /**
     * Register a connection.
     *
     * @param  array   $options
     * @param  string  $name
     * @return self
     */
    public function addConnection(array $options, $name = 'default')
    {
        $this->connectionResolver->addConnection(
            $this->make($options),
            $name
        );

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
        return $this->connectionResolver->connection($name);
    }

    /**
     * Establish an API connection based on the configuration.
     *
     * @param  array   $options
     * @return ConnectionInterface
     */
    public function make(array $options)
    {
        return $this->createSingleConnection($options);
    }

    /**
     * Create a single API connection instance.
     *
     * @param  array  $options
     * @return ConnectionInterface
     */
    protected function createSingleConnection(array $options)
    {
        $connector = $this->createConnector($options);

        return $this->createConnection($connector, $options);
    }

    /**
     * Create a connector instance based on the configuration.
     *
     * @param  array  $options
     * @return ConnectorInterface
     * @throws InvalidArgumentException
     */
    public function createConnector(array $options)
    {
        $provider = isset($options['provider']) ? $options['provider'] : null;

        if (! is_null($provider)) {
            $provider = is_string($provider) ? new $provider($options) : $provider;

            if (! $provider instanceof AbstractConnector) {
                throw new RepositoryException("Class {$provider} must be an instance of Kevindierkx\\Elicit\\Connector\\AbstractConnector");
            }

            return $provider;
        }

        return new GenericConnector($options);
    }

    /**
     * Create a new connection instance.
     *
     * @param  AbstractConnector  $connector
     * @param  array              $config
     * @return AbstractConnection
     * @throws InvalidArgumentException
     */
    protected function createConnection(AbstractConnector $connector, array $config = [])
    {
        // TODO: Remove and merge with connector/provider.
        return new GenericConnection($connector, $config);
    }
}
