<?php namespace Kevindierkx\Elicit\Connector;

interface ConnectorInterface {

	/**
	 * Establish an API connection.
	 *
	 * @param  array  $config
	 * @return \Kevindierkx\Elicit\Connector\Connector
	 */
	public function connect(array $config);

}
