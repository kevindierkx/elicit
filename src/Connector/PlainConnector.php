<?php

namespace Kevindierkx\Elicit\Connector;

class PlainConnector extends AbstractConnector
{
    /**
     * Establish an API connection.
     *
     * @param  array  $config
     * @return self
     */
    public function connect(array $config)
    {
        $connection = $this->createConnection($config);

        return $connection;
    }
}
