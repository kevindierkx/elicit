<?php namespace Kevindierkx\Elicit;

use Illuminate\Support\Str;
use Kevindierkx\Elicit\Connection\Connection;
use Kevindierkx\Elicit\ConnectionFactory;

class ApiManager implements ConnectionResolverInterface
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The database connection factory instance.
     *
     * @var \Kevindierkx\Elicit\Connection\ConnectionFactory
     */
    protected $factory;

    /**
     * The active connection instances.
     *
     * @var array
     */
    protected $connections = array();

    /**
     * The custom connection resolvers.
     *
     * @var array
     */
    protected $extensions = array();

    /**
     * Create a new database manager instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @param  \Kevindierkx\Elicit\Connection\ConnectionFactory  $factory
     * @return void
     */
    public function __construct($app, ConnectionFactory $factory)
    {
        $this->app = $app;

        $this->factory = $factory;
    }

    /**
     * Get an API connection instance.
     *
     * @param  string  $name
     * @return \Illuminate\Database\Connection
     */
    public function connection($name = null)
    {
        if (! isset($this->connections[$name])) {
            $connection = $this->makeConnection($name);

            $this->connections[$name] = $this->prepare($connection);
        }

        return $this->connections[$name];
    }

    /**
     * Make the API connection instance.
     *
     * @param  string  $name
     * @return \Kevindierkx\Elicit\Connection\Connection
     */
    protected function makeConnection($name)
    {
        $config = $this->getConfig($name);

        // First we will check by the connection name to see if an extension has been
        // registered specifically for that connection. If it has we will call the
        // Closure and pass it the config allowing it to resolve the connection.
        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        $driver = $config['driver'];

        // Next we will check to see if an extension has been registered for a driver
        // and will call the Closure if so, which allows us to have a more generic
        // resolver for the drivers themselves which applies to all connections.
        if (isset($this->extensions[$driver])) {
            return call_user_func($this->extensions[$driver], $config, $name);
        }

        return $this->factory->make($config, $name);
    }

    /**
     * Prepare the API connection instance.
     *
     * @param  \Kevindierkx\Elicit\Connection\Connection  $connection
     * @return \Kevindierkx\Elicit\Connection\Connection
     */
    protected function prepare(Connection $connection)
    {
        if ($this->app->bound('events')) {
            $connection->setEventDispatcher($this->app['events']);
        }

        return $connection;
    }

    /**
     * Get the configuration for a connection.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function getConfig($name)
    {
        $name = $name ?: $this->getDefaultConnection();

        // To get the API connection configuration, we will just pull each of the
        // connection configurations and get the configurations for the given name.
        // If the configuration doesn't exist, we'll throw an exception and bail.
        $connections = $this->app['config']['elicit::connections'];

        if (is_null($config = array_get($connections, $name))) {
            throw new \InvalidArgumentException("API connection [$name] not configured.");
        }

        return $config;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->app['config']['elicit::default'];
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultConnection($name)
    {
        $this->app['config']['elicit::default'] = $name;
    }

    /**
     * Return all of the created connections.
     *
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * Register an extension connection resolver.
     *
     * @param  string    $name
     * @param  callable  $resolver
     * @return void
     */
    public function extend($name, callable $resolver)
    {
        $this->extensions[$name] = $resolver;
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection(), $method], $parameters);
    }
}
