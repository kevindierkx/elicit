<?php namespace Kevindierkx\Elicit\Connector;

class BasicConnector extends Connector implements ConnectorInterface {

	/**
	 * {@inheritdoc}
	 */
	public function connect(array $config)
	{
		$connection = $this->createConnection($config);

		return $connection;
	}

}
