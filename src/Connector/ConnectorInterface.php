<?php namespace Kevindierkx\Elicit\Connector;

interface ConnectorInterface
{
    /**
     * Establish an API connection.
     *
     * @param array $config
     * @return Connector
     */
    public function connect(array $config);
}
