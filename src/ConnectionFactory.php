<?php

namespace Kevindierkx\Elicit;

use InvalidArgumentException;
use Kevindierkx\Elicit\Connection\AbstractConnection;
use Kevindierkx\Elicit\Connection\GenericConnection;
use Kevindierkx\Elicit\ConnectionResolver;
use Kevindierkx\Elicit\ConnectionResolverInterface;
use Kevindierkx\Elicit\Connector\AbstractConnector;
use Kevindierkx\Elicit\Connector\BasicAuthConnector;
use Kevindierkx\Elicit\Connector\PlainConnector;

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
     * @param  array   $config
     * @param  string  $name
     * @return self
     */
    public function addConnection(array $config, $name = 'default')
    {
        $this->connectionResolver->addConnection(
            $this->make($config),
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
     * @param  array   $config
     * @return ConnectionInterface
     */
    public function make(array $config)
    {
        return $this->createSingleConnection($config);
    }

    /**
     * Create a single API connection instance.
     *
     * @param  array  $config
     * @return ConnectionInterface
     */
    protected function createSingleConnection(array $config)
    {
        $connector = $this->createConnector($config)->connect($config);

        return $this->createConnection($connector, $config);
    }

    /**
     * Create a connector instance based on the configuration.
     *
     * @param  array  $config
     * @return ConnectorInterface
     * @throws InvalidArgumentException
     */
    public function createConnector(array $config)
    {
        // The authentication driver is optional, but the connector is still required.
        // We return the plain connector when no authentication driver is defined.
        if (isset($config['auth'])) {
            switch ($driver = $config['auth']) {
                case 'basic-auth':
                    return new BasicAuthConnector;
                default:
                    throw new InvalidArgumentException("Unsupported authentication driver [{$driver}]");
            }
        }

        // TODO: Implement an extension stage.
        return new PlainConnector;
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
        // The connection driver is optional, but the connection is still required.
        // We return the generic connection when no connection driver is defined.
        if (isset($config['driver'])) {
            switch ($driver = $config['driver']) {
                default:
                    throw new InvalidArgumentException("Unsupported connection driver [{$driver}]");
            }
        }

        return new GenericConnection($connector, $config);
    }
}
