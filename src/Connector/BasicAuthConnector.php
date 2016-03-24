<?php

namespace Kevindierkx\Elicit\Connector;

use RuntimeException;

class BasicAuthConnector extends AbstractConnector
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

        $this->validateCredentials($config);

        // For basic authentication we need an Authorization
        // header to be set. We add it to the request here.
        $this->addHeader(
            'Authorization',
            'Basic ' . base64_encode(
                array_get($config, 'identifier') . ':' . array_get($config, 'secret')
            )
        );

        return $connection;
    }

    /**
     * Validate the credentials in the configuration.
     *
     * @param  array  $config
     *
     * @throws RuntimeException
     */
    protected function validateCredentials(array $config)
    {
        $hasCredentials = array_get($config, 'identifier') && array_get($config, 'secret');

        if (! $hasCredentials) {
            throw new RuntimeException(
                "An identifier and secret are required for basic authentication."
            );
        }
    }
}
