<?php

namespace Kevindierkx\Elicit\Connector\Exception;

class ProviderException extends \Exception
{
    /**
     * @var mixed
     */
    protected $response;

    /**
     * @param string        $message
     * @param int           $code
     * @param array|string  $response
     */
    public function __construct($message, $code, $response)
    {
        $this->response = $response;

        parent::__construct($message, $code);
    }

    /**
     * Returns the exception's response body.
     *
     * @return array|string
     */
    public function getResponseBody()
    {
        return $this->response;
    }
}
