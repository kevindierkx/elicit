<?php

namespace Kevindierkx\Elicit;

use Kevindierkx\Elicit\Connection\AbstractConnection;

interface ConnectionResolverInterface
{
    /**
     * Get an API connection instance.
     *
     * @param  string  $name
     * @return \Kevindierkx\Elicit\Connection\Connection
     */
    public function connection($name = null);

    /**
     * Add a connection instance to the resolver.
     *
     * @param  AbstractConnection  $connection
     * @param  string              $name
     */
    public function addConnection(AbstractConnection $connection, $name);

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection();

    /**
     * Set the default connection name.
     *
     * @param  string $name
     * @return ConnectionResolverInterface
     */
    public function setDefaultConnection($name);
}
