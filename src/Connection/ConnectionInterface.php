<?php namespace Kevindierkx\Elicit\Connection;

interface ConnectionInterface
{
    /**
     * Run a request against the API.
     *
     * @param  array  $query
     * @return array
     */
    public function request(array $query);
}
