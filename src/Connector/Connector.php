<?php namespace Kevindierkx\Elicit\Connector;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;

class Connector {

	/**
	 * The request client instance used during requests.
	 *
	 * @var \GuzzleHttp\Client
	 */
	protected $client;

	/**
	 * The request instance used during requests.
	 *
	 * @var \GuzzleHttp\Message\Request
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
	 * Initialize connector.
	 *
	 * @param  array  $config
	 * @return \Kevindierkx\Elicit\Connector\Connector
	 */
	public function createConnection(array $config)
	{
		$hasHost = isset($config['host']);

		if (! $hasHost) {
			throw new \InvalidArgumentException("No host provided for connection [" . $config['name'] . "]");
		}

		$this->setHost($config['host']);

		$hasHeaders = isset($config['headers']);

		// We check the configuration for request headers. Some API's require
		// certain headers for all requests. Providing them in the configuration
		// makes it easier to provide these headers on each request.
		if ($hasHeaders) {
			$this->setHeaders($config['headers']);
		}

		return $this;
	}

	/**
	 * Prepare a new request for execution.
	 *
	 * @param  array  $query
	 * @return \Kevindierkx\Elicit\Connector\Connector
	 */
	public function prepare(array $query)
	{
		$client = new Client;

		$request = $client->createRequest(
			$this->prepareMethod($query),
			$this->prepareRequestUrl($query)
		);

		$hasHeaders = ! is_null($this->headers);

		if ($hasHeaders) {
			$request->addHeaders($this->headers);
		}

		$this->client = $client;
		$this->request = $request;

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
		return $this->host . array_get($query, 'from.path') . '?' . array_get($query, 'wheres');
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
	 * @throws \RuntimeException
	 */
	protected function parseResponse(Response $response)
	{
		$contentType = explode(';', $response->getHeader('content-type'))[0];

		switch ($contentType) {
			case 'application/json':
				return $response->json();

			case 'application/xml':
				return $response->xml();
		}

		throw new \RuntimeException("Unsupported returned content-type [$contentType]");
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
	 * @param  \GuzzleHttp\Client  $client
	 */
	public function setClient($client)
	{
		$this->client = $client;
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
	 * @param  \GuzzleHttp\Message\Request  $request
	 */
	public function setRequest($request)
	{
		$this->request = $request;
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
	 */
	public function setHost($host)
	{
		$this->host = $host;
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
	 */
	public function setHeaders(array $headers)
	{
		foreach ($headers as $key => $value) {
			$this->headers[$key] = $value;
		}
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
