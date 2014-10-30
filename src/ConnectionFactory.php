<?php namespace Kevindierkx\Elicit;

use Illuminate\Container\Container;
use Kevindierkx\Elicit\Connector\ConnectorInterface;
use Kevindierkx\Elicit\Connector\BasicConnector;
use Kevindierkx\Elicit\Connector\BasicAuthConnector;
use Kevindierkx\Elicit\Connection\Connection;
use Kevindierkx\Elicit\Connection\FractalConnection;

class ConnectionFactory {

	/**
	 * The IoC container instance.
	 *
	 * @var \Illuminate\Container\Container
	 */
	protected $container;

	/**
	 * Create a new connection factory instance.
	 *
	 * @param  \Illuminate\Container\Container  $container
	 * @return void
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Establish a API connection based on the configuration.
	 *
	 * @param  array   $config
	 * @param  string  $name
	 * @return \Illuminate\Database\Connection
	 */
	public function make(array $config, $name = null)
	{
		$config = $this->parseConfig($config, $name);

		return $this->createSingleConnection($config);
	}

	/**
	 * Create a single database connection instance.
	 *
	 * @param  array  $config
	 * @return \Kevindierkx\Elicit\Connection\ConnectionInterface
	 */
	protected function createSingleConnection(array $config)
	{
		$connector = $this->createConnector($config)->connect($config);

		return $this->createConnection($config['driver'], $connector, $config);
	}

	/**
	 * Parse and prepare the API configuration.
	 *
	 * @param  array   $config
	 * @param  string  $name
	 * @return array
	 */
	protected function parseConfig(array $config, $name)
	{
		return array_merge($config, ['name' => $name]);
	}

	/**
	 * Create a connector instance based on the configuration.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Database\Connectors\ConnectorInterface
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createConnector(array $config)
	{
		// The authentication driver is optional, but the connector
		// is still required. We set the auth driver to the default
		// here. This way we can use container bindings in return.
		$driver = array_get($config, 'auth', 'basic');

		if ($this->container->bound($key = "elicit.connector.{$driver}")) {
			return $this->container->make($key);
		}

		switch ($driver) {
			case 'basic':
				return new BasicConnector;

			case 'basic-auth':
				return new BasicAuthConnector;
		}

		throw new \InvalidArgumentException("Unsupported authentication driver [{$driver}]");
	}

	/**
	 * Create a new connection instance.
	 *
	 * @param  string  $driver
	 * @param  \Kevindierkx\Elicit\Connector\ConnectorInterface  $connector
	 * @param  array   $config
	 * @return \Kevindierkx\Elicit\Connection\ConnectionInterface
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function createConnection($driver, ConnectorInterface $connector, array $config = array())
	{
		if (! isset($driver)) {
			throw new \InvalidArgumentException("A driver must be specified.");
		}

		if ($this->container->bound($key = "elicit.connection.{$driver}")) {
			return $this->container->make($key, [$connector, $config]);
		}

		switch ($driver) {
			case 'basic':
				return new Connection($connector, $config);

			case 'fractal':
				return new FractalConnection($connector, $config);
		}

		throw new \InvalidArgumentException("Unsupported driver [$driver]");
	}

}
