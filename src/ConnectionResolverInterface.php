<?php namespace Kevindierkx\Elicit;

interface ConnectionResolverInterface
{
    /**
     * Get a API connection instance.
     *
     * @param  string  $name
     * @return \Kevindierkx\Elicit\Connection\Connection
     */
    public function connection($name = null);

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
