<?php namespace PCextreme\Api\Connection;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

use Illuminate\Contracts\Events\Dispatcher;
use PCextreme\Api\Query\Processors\Processor;
use PCextreme\Api\Query\Processors\FractalProcessor;

class Connection implements ConnectionInterface {

	const METHOD_GET     = 'GET';

	const METHOD_POST    = 'POST';

	const METHOD_PUT     = 'PUT';

	const METHOD_PATCH   = 'PATCH';

	const METHOD_DELETE  = 'DELETE';

	const METHOD_OPTIONS = 'OPTIONS';

	/**
	 * The host URL where API request will be called.
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * The API connection configuration options.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * The query post processor implementation.
	 *
	 * @var \PCextreme\Api\Query\Processors\Processor
	 */
	protected $postProcessor;

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Illuminate\Contracts\Events\Dispatcher
	 */
	protected $events;

	/**
	 * Create new API connection instance.
	 *
	 * @param string  $host
	 * @param array   $config
	 */
	public function __construct($host, $config)
	{
		$this->host = $host;

		$this->config = $config;

		// We need to initialize a query post processors
		// which is a very important part of the API abstractions
		// so we initialize these to their default values while starting.
		$this->useDefaultPostProcessor();
	}

	/**
	 * Set the query post processor to the default implementation.
	 *
	 * @return void
	 */
	public function useDefaultPostProcessor()
	{
		$this->postProcessor = $this->getDefaultPostProcessor();
	}

	/**
	 * Get the default post processor instance.
	 *
	 * @return \PCextreme\Api\Query\Processors\Processor
	 */
	protected function getDefaultPostProcessor()
	{
		$hasPocessorConfig = isset($this->config['processor']);

		// When the connection config has a processor we will try
		// to validate the given processor.
		if ($hasPocessorConfig) {

			$processor = $this->config['processor'];

			// If the processor is a class and instance of Processor
			// well return it here.
			if (class_exists($processor) && $processor instanceof Processor) {
				return new $processor;
			}

			// Here well assume the given processor is the identifier of a default
			// processor. Well look it up here and return it when available.
			else {
				switch ($processor) {
					case 'fractal':
						return new FractalProcessor;
				}
			}

			throw new \InvalidArgumentException("Unsupported processor [$processor]");
		}

		return new Processor;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($path, $query = array())
	{
		return $this->request(self::METHOD_GET, $path, $query);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post($path, $query = array(), $postBody = array())
	{
		return $this->request(self::METHOD_POST, $path, $query, $postBody);
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($path, $query = array(), $postBody = array())
	{
		return $this->request(self::METHOD_PUT, $path, $query, $postBody);
	}

	/**
	 * {@inheritdoc}
	 */
	public function patch($path, $query = array(), $postBody = array())
	{
		return $this->request(self::METHOD_PATCH, $path, $query, $postBody);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path, $query = array())
	{
		return $this->request(self::METHOD_DELETE, $path, $query);
	}

	/**
	 * {@inheritdoc}
	 */
	public function options($path, $query = array())
	{
		return $this->request(self::METHOD_OPTIONS, $path, $query);
	}

	protected function request($method, $path, $query = array(), $postBody = array())
	{
		$hasNamedParameters = $this->hasNamedParameters($path);

		if ($hasNamedParameters) {
			$path = $this->replaceNamedParameters($path, $query);
		}

		return $this->execute($method, $path, $query, $postBody);
	}

	/**
	 * Execute an API request and log its execution context.
	 *
	 *
	 */
	protected function execute($method, $path, $query = array(), $postBody = array())
	{
		$requestUrl = $this->host . $path;

		try {
			$client = new Client;
			$request = $client->createRequest($method, $requestUrl);
			$params = $request->getQuery();

			// Set query params
			foreach ($query as $key => $value) {
				$params->set($key, $value);
			}

			// Set post body when set
			if (! empty($postBody)) {
				$requestBody = $request->getBody();

				foreach ($postBody as $item) {
					if (
						! isset($item['name']) ||
						! isset($item['value'])
					) {
						throw new InvalidArgumentException("The provided body is invalid.");
					}

					if (
						isset($item['type']) &&
						$item['type'] == 'file'
					) {
						$requestBody->addFile(new PostFile($item['name'], fopen($item['value'], 'r')));
					} else {
						$requestBody->setField($item['name'], $item['value']);
					}
				}
			}

			return $client->send($request)->json();
		} catch (ClientException $e) {
			$response   = $e->getResponse();

			$statusCode = $response->getStatusCode();

			switch($statusCode) {
				// When we receive a 401 status code from the API well assume
				// we are not authorized to see the resource. When using OAuth
				// we could try to fetch a new token from the API before stopping.
				case '401':
					throw (new InvalidCredentialsException)->setConnection($this->config['name']);

				// When we receive a 404 status code from the API well assume
				// the resource was not found. Returning an empty array here
				// will make it possible to use the findOrFail from the Elicit model.
				case '404':
					return array();

				//
				// TODO: Something went wrong during the request.
				//
				default:
					throw new RuntimeException("Something went wrong [" . $response->getReasonPhrase() . "]");
			}
		}
	}

	/**
	 * Check for named parameters in the path.
	 *
	 * @param  string  $path
	 * @return boolean
	 */
	protected function hasNamedParameters($path)
	{
		return preg_match('/\{(.*?)\??\}/', $path);
	}

	/**
	 * Replace all of the named parameters in the path.
	 * Removes them from the query in the process.
	 *
	 * @param  string  $path
	 * @param  array   $query
	 * @return string
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function replaceNamedParameters($path, array &$query = array())
	{
		return preg_replace_callback('/\{(.*?)\??\}/', function($m) use (&$query) {
			if (isset($query[$m[1]])) {
				$parameter = $query[$m[1]];

				unset($query[$m[1]]);

				return $parameter;
			}

			// When the named parameter is not provided in the wheres array
			// well stop here. Named parameters are most likely required for
			// the request.
			throw new \InvalidArgumentException("Named parameter [$m[1]] missing from request for path [$path]");
		}, $path);
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
	 * Get the query post processor used by the connection.
	 *
	 * @return \Illuminate\Database\Query\Processors\Processor
	 */
	public function getPostProcessor()
	{
		return $this->postProcessor;
	}

	/**
	 * Set the query post processor used by the connection.
	 *
	 * @param  \Illuminate\Database\Query\Processors\Processor
	 * @return void
	 */
	public function setPostProcessor(Processor $processor)
	{
		$this->postProcessor = $processor;
	}

	/**
	 * Get the event dispatcher used by the connection.
	 *
	 * @return \Illuminate\Contracts\Events\Dispatcher
	 */
	public function getEventDispatcher()
	{
		return $this->events;
	}

	/**
	 * Set the event dispatcher instance on the connection.
	 *
	 * @param  \Illuminate\Contracts\Events\Dispatcher
	 * @return void
	 */
	public function setEventDispatcher(Dispatcher $events)
	{
		$this->events = $events;
	}

}
