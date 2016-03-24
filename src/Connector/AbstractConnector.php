<?php

namespace Kevindierkx\Elicit\Connector;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractConnector
{
    /**
     * The request client instance used during requests.
     *
     * @var Client
     */
    protected $client;

    /**
     * The request instance used during requests.
     *
     * @var Request
     */
    protected $request;

    /**
     * The host URL where API request will be called.
     *
     * @var string
     */
    protected $host;

    /**
     * The request headers send during requests.
     *
     * @var array
     */
    protected $headers;

    /**
     * Establish an API connection.
     *
     * @param  array  $config
     * @return self
     */
    abstract public function connect(array $config);

    /**
     * Initialize connector.
     *
     * @param  array  $config
     * @return self
     * @throws InvalidArgumentException
     */
    public function createConnection(array $config)
    {
        if (! isset($config['host'])) {
            throw new InvalidArgumentException("No host provided.");
        }

        $this->setHost($config['host']);

        // We check the configuration for request headers. Some API's require
        // certain headers for all requests. Providing them in the configuration
        // makes it easier to provide these headers on each request.
        if (isset($config['headers'])) {
            $this->setHeaders($config['headers']);
        }

        return $this;
    }

    /**
     * Prepare a new request for execution.
     *
     * @param  array  $query
     * @return self
     */
    public function prepare(array $query)
    {
        $client = new Client;

        $this->client  = $client;
        $this->request = $client->createRequest(
            $this->prepareMethod($query),
            $this->prepareRequestUrl($query),
            [
                'headers' => $this->prepareHeaders($query),
                'body'    => $this->prepareBody($query),
            ]
        );

        return $this;
    }

    /**
     * Prepare request method from query.
     *
     * @param  array  $query
     * @return string
     */
    protected function prepareMethod(array $query)
    {
        return array_get($query, 'from.method');
    }

    /**
     * Prepare request URL from query.
     *
     * @param  array  $query
     * @return string
     */
    protected function prepareRequestUrl(array $query)
    {
        $wheres = array_get($query, 'wheres');

        $baseUrl = $this->host . array_get($query, 'from.path');

        $hasWheres = ! empty($wheres);

        // Here we validate that there are any wheres in the
        // request. When none are provided we will return the
        // Request Url without the question mark.
        if ($hasWheres) {
            return $baseUrl . '?' . array_get($query, 'wheres');
        }

        return $baseUrl;
    }

    /**
     * Prepare headers from query.
     *
     * @param  array  $query
     * @return array
     */
    protected function prepareHeaders(array $query)
    {
        if (! is_null($this->headers)) {
            return $this->headers;
        }

        // Headers should always be added as an array. When the user
        // has not defined any headers in either the connection config or
        // during runtime we will return an empty array as placeholder.
        return [];
    }

    /**
     * Prepare body from query.
     *
     * @param  array  $query
     * @return string|array|null
     */
    protected function prepareBody(array $query)
    {
        $isPostMethod = array_get($query, 'from.method') == 'POST';

        if ($isPostMethod) {
            // The query grammar already parsed the body for us.
            // We return the value of the query and guzzle does the rest.
            return array_get($query, 'body');
        }
    }

    /**
     * Execute an API request.
     *
     * @return array
     *
     * @throws \GuzzleHttp\Exception\RequestException
     * @throws \GuzzleHttp\Exception\ClientException  400 Errors
     * @throws \GuzzleHttp\Exception\ServerException  500 Errors
     * @throws \GuzzleHttp\Exception\TooManyRedirectsException
     */
    public function execute()
    {
        return $this->parseResponse($this->client->send($this->request));
    }

    /**
     * Parse the returned response.
     *
     * @param  \GuzzleHttp\Message\Response  $response
     * @return array
     *
     * @throws RuntimeException
     */
    protected function parseResponse(Response $response)
    {
        $contentType = explode(';', $response->getHeader('content-type'))[0];

        switch ($contentType) {
            case 'application/json':
            case 'application/vnd.api+json':
                return $response->json();

            case 'application/xml':
                return $response->xml();
        }

        throw new RuntimeException("Unsupported returned content-type [$contentType]");
    }

    /**
     * Get request client instance.
     *
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set request client instance.
     *
     * @param  Client  $client
     * @return self
     */
    public function setClient(Client $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get request instance.
     *
     * @return \GuzzleHttp\Message\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set request instance.
     *
     * @param  Request  $request
     * @return self
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get host URL.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set host URL.
     *
     * @param  string  $host
     * @return self
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Get request headers.
     *
     * @return string
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set request headers.
     *
     * @param  array  $headers
     * @return self
     */
    public function setHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->headers[$key] = $value;
        }

        return $this;
    }

    /**
     * Add request header.
     *
     * @param  string  $key
     * @param  string  $value
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }
}
