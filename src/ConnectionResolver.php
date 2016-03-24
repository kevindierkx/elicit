<?php

namespace Kevindierkx\Elicit;

use InvalidArgumentException;
use Kevindierkx\Elicit\Connection\AbstractConnection;

class ConnectionResolver implements ConnectionResolverInterface
{
    /**
     * All of the registered connection instances.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * The default connection name.
     *
     * @var string
     */
    protected $default;

    /**
     * Create a new connection resolver instance.
     *
     * @param  array  $connections
     */
    public function __construct(array $connections = [])
    {
        foreach ($connections as $name => $connection) {
            $this->addConnection($name, $connection);
        }
    }

    /**
     * Get an API connection instance.
     *
     * @param  string  $name
     * @return ConnectionInterface
     */
    public function connection($name = null)
    {
        if (is_null($name)) {
            $name = $this->getDefaultConnection();
        }

        if (! isset($this->connections[$name])) {
            throw new InvalidArgumentException("Connection [{$name}] is not registered.");
        }

        return $this->connections[$name];
    }

    /**
     * Check if a connection instance has been registered.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasConnection($name)
    {
        return isset($this->connections[$name]);
    }

    /**
     * Add a connection instance to the resolver.
     *
     * @param  AbstractConnection  $connection
     * @param  string              $name
     */
    public function addConnection(AbstractConnection $connection, $name)
    {
        $this->connections[$name] = $connection;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->default;
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return self
     */
    public function setDefaultConnection($name)
    {
        $this->default = $name;

        return $this;
    }
}
