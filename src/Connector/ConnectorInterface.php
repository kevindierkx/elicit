<?php namespace Kevindierkx\Elicit\Connector;

use Closure;

interface ConnectorInterface {

	/**
	 * Establish an API connection.
	 *
	 * @param  array  $config
	 * @return \Kevindierkx\Elicit\Connector\Connector
	 */
	public function connect(array $config);

}
