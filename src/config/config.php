<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default API Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the API connections below you wish
    | to use as your default connection. Of course you may use many
    | connections at once using the Elicit library.
    |
    */

    'default' => 'basic',

    /*
    |--------------------------------------------------------------------------
    | API Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the API connections setup for your application.
    | Elicit has support for different connection drivers and authentication
    | drivers defined with the driver and the auth key in your connection.
    |
    | The available connection drivers are defined by the Connections
    | classes. Connection drivers handle request to and from the API.
    | The available authentication drivers are defined by the Connectors
    | classes. Authentication drivers handle the API authentication.
    |
    */

    'connections' => [

        'basic' => [
            'driver' => 'basic',
            'host'   => '',
            'auth'   => 'basic',
            'headers' => [],
        ],

        'basic-auth' => [
            'driver' => 'basic',
            'host'   => '',
            'auth'       => 'basic-auth',
            'identifier' => '',
            'secret'     => '',
            'headers' => [],
        ],

    ],

];
