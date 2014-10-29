<?php namespace Kevindierkx\Elicit\Connection;

use Illuminate\Container\Container;

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

		// if (isset($config['read'])) {
		// 	return $this->createReadWriteConnection($config);
		// }

		return $this->createSingleConnection($config);
	}

	/**
	 * Create a single database connection instance.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Database\Connection
	 */
	protected function createSingleConnection(array $config)
	{
		// $pdo = $this->createConnector($config)->connect($config);

		return $this->createConnection($config['driver'], $config['host'], $config);
	}

	/**
	 * Create a single database connection instance.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Database\Connection
	 */
	// protected function createReadWriteConnection(array $config)
	// {
	// 	$connection = $this->createSingleConnection($this->getWriteConfig($config));

	// 	return $connection->setReadPdo($this->createReadPdo($config));
	// }

	/**
	 * Create a new PDO instance for reading.
	 *
	 * @param  array  $config
	 * @return \PDO
	 */
	// protected function createReadPdo(array $config)
	// {
	// 	$readConfig = $this->getReadConfig($config);

	// 	return $this->createConnector($readConfig)->connect($readConfig);
	// }

	/**
	 * Get the read configuration for a read / write connection.
	 *
	 * @param  array  $config
	 * @return array
	 */
	// protected function getReadConfig(array $config)
	// {
	// 	$readConfig = $this->getReadWriteConfig($config, 'read');

	// 	return $this->mergeReadWriteConfig($config, $readConfig);
	// }

	/**
	 * Get the read configuration for a read / write connection.
	 *
	 * @param  array  $config
	 * @return array
	 */
	// protected function getWriteConfig(array $config)
	// {
	// 	$writeConfig = $this->getReadWriteConfig($config, 'write');

	// 	return $this->mergeReadWriteConfig($config, $writeConfig);
	// }

	/**
	 * Get a read / write level configuration.
	 *
	 * @param  array   $config
	 * @param  string  $type
	 * @return array
	 */
	// protected function getReadWriteConfig(array $config, $type)
	// {
	// 	if (isset($config[$type][0])) {
	// 		return $config[$type][array_rand($config[$type])];
	// 	}

	// 	return $config[$type];
	// }

	/**
	 * Merge a configuration for a read / write connection.
	 *
	 * @param  array  $config
	 * @param  array  $merge
	 * @return array
	 */
	// protected function mergeReadWriteConfig(array $config, array $merge)
	// {
	// 	return array_except(array_merge($config, $merge), array('read', 'write'));
	// }

	/**
	 * Parse and prepare the database configuration.
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
	 * Create a new connection instance.
	 *
	 * @param  string   $driver
	 * @param  string   $host
	 * @param  array    $config
	 * @return \Kevindierkx\Elicit\Connection\Connection
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function createConnection($driver, $host, array $config = array())
	{
		if ($this->container->bound($key = "elicit.connection.{$driver}")) {
			return $this->container->make($key, array($host, $config));
		}

		switch ($driver) {
			case 'basic':
				return new Connection($host, $config);
		}

		throw new \InvalidArgumentException("Unsupported driver [$driver]");
	}

}
