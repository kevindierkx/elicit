<?php namespace Kevindierkx\Elicit\Connection;

use Closure;
use Illuminate\Events\Dispatcher;
use Kevindierkx\Elicit\QueryException;
use Kevindierkx\Elicit\Connector\Connector;
use Kevindierkx\Elicit\Query\Grammars\Grammar;
use Kevindierkx\Elicit\Query\Processors\Processor;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class Connection implements ConnectionInterface {

	/**
	 * The active connector instance.
	 *
	 * @var \Kevindierkx\Elicit\Connector\Connector
	 */
	protected $connector;

	/**
	 * The query grammar implementation.
	 *
	 * @var \Kevindierkx\Elicit\Query\Grammars\Grammar
	 */
	protected $queryGrammar;

	/**
	 * The query post processor implementation.
	 *
	 * @var \Kevindierkx\Elicit\Query\Processors\Processor
	 */
	protected $postProcessor;

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Illuminate\Contracts\Events\Dispatcher
	 */
	protected $events;

	/**
	 * All of the queries ran against the connection.
	 *
	 * @var array
	 */
	protected $queryLog = array();

	/**
	 * Indicates whether queries are being logged.
	 *
	 * @var bool
	 */
	protected $loggingQueries = false;

	/**
	 * The API connection configuration options.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Create new API connection instance.
	 *
	 * @param \Kevindierkx\Elicit\Connector\Connector  $connector
	 * @param array   $config
	 */
	public function __construct($connector, $config)
	{
		$this->connector = $connector;

		$this->config = $config;

		// We need to initialize a query grammar and the query post processors
		// which are both very important parts of the API abstractions
		// so we initialize these to their default values while starting.
		$this->useDefaultQueryGrammar();

		$this->useDefaultPostProcessor();
	}

	/**
	 * Set the query grammar to the default implementation.
	 *
	 * @return void
	 */
	public function useDefaultQueryGrammar()
	{
		$this->queryGrammar = $this->getDefaultQueryGrammar();
	}

	/**
	 * Get the default query grammar instance.
	 *
	 * @return \Kevindierkx\Elicit\Query\Grammars\Grammar
	 */
	protected function getDefaultQueryGrammar()
	{
		return new Grammar;
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
	 * @return \Kevindierkx\Elicit\Query\Processors\Processor
	 */
	protected function getDefaultPostProcessor()
	{
		return new Processor;
	}

	/**
	 * {@inheritdoc}
	 */
	public function request(array $query)
	{
		return $this->run($query, function($query)
		{
			$connector = $this->getConnector()->prepare($query);

			return $connector->execute();
		});
	}

	/**
	 * Run a SQL statement and log its execution context.
	 *
	 * @param  array     $query
	 * @param  \Closure  $callback
	 * @return array
	 */
	protected function run(array $query, Closure $callback)
	{
		$start = microtime(true);

		// Here we will run this query. If an exception occurs we'll determine a
		// few basic scenarios and create an appropriate response for them.
		$result = $this->runQueryCallback($query, $callback);

		// Once we have ran the query we will calculate the time that it took to run and
		// then log the query and execution time so we will report them on
		// the event that the developer needs them. We'll log time in milliseconds.
		$time = $this->getElapsedTime($start);

		$this->logQuery($query, $time);

		return $result;
	}

	/**
	 * Run a SQL statement.
	 *
	 * @param  string    $query
	 * @param  \Closure  $callback
	 * @return array
	 *
	 * @throws \Kevindierkx\Elicit\Connection\InvalidCredentialsException
	 * @throws \Kevindierkx\Elicit\QueryException
	 *
	 * @throws \GuzzleHttp\Exception\RequestException
	 * @throws \GuzzleHttp\Exception\ClientException  400 Errors
	 * @throws \GuzzleHttp\Exception\TooManyRedirectsException
	 */
	protected function runQueryCallback(array $query, Closure $callback)
	{
		try {
			$result = $callback($query);
		} catch (ClientException $e) {
			$statusCode = $e->getResponse()->getStatusCode();

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

				default:
					throw $e;
			}
		} catch (ServerException $e) {
			throw new QueryException($query, $e);
		}

		return $result;
	}

	/**
	 * Log a query in the connection's query log.
	 *
	 * @param  string  $query
	 * @param  $time
	 * @return void
	 */
	public function logQuery($query, $time = null)
	{
		if (isset($this->events)) {
			$this->events->fire('elicit.query', [$query, $time, $this->getName()]);
		}

		if (! $this->loggingQueries) return;

		$this->queryLog[] = compact('query', 'time');
	}

	/**
	 * Get the elapsed time since a given starting point.
	 *
	 * @param  int    $start
	 * @return float
	 */
	protected function getElapsedTime($start)
	{
		return round((microtime(true) - $start) * 1000, 2);
	}

	/**
	 * Get the current API connector.
	 *
	 * @return \Kevindierkx\Elicit\Connector\Connector
	 */
	public function getConnector()
	{
		return $this->connector;
	}

	/**
	 * Set the API connector.
	 *
	 * @param  \Kevindierkx\Elicit\Connector\Connector|null  $connector
	 * @return $this
	 */
	public function setConnector($connector)
	{
		$this->connector = $connector;

		return $this;
	}

	/**
	 * Get the API connection name.
	 *
	 * @return string|null
	 */
	public function getName()
	{
		return $this->getConfig('name');
	}

	/**
	 * Get an option from the configuration options.
	 *
	 * @param  string  $option
	 * @return mixed
	 */
	public function getConfig($option)
	{
		return array_get($this->config, $option);
	}

	/**
	 * Get the connection driver name.
	 *
	 * @return string
	 */
	public function getDriverName()
	{
		return $this->getConfig('driver');
	}

	/**
	 * Get the connection authentication name.
	 *
	 * @return string
	 */
	public function getAuthName()
	{
		return $this->getConfig('auth');
	}

	/**
	 * Get the query grammar used by the connection.
	 *
	 * @return \Kevindierkx\Elicit\Query\Grammars\Grammar
	 */
	public function getQueryGrammar()
	{
		return $this->queryGrammar;
	}

	/**
	 * Set the query grammar used by the connection.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Grammars\Grammar
	 * @return void
	 */
	public function setQueryGrammar(Grammar $grammar)
	{
		$this->queryGrammar = $grammar;
	}

	/**
	 * Get the query post processor used by the connection.
	 *
	 * @return \Kevindierkx\Elicit\Query\Processors\Processor
	 */
	public function getPostProcessor()
	{
		return $this->postProcessor;
	}

	/**
	 * Set the query post processor used by the connection.
	 *
	 * @return \Kevindierkx\Elicit\Query\Processors\Processor
	 * @return void
	 */
	public function setPostProcessor(Processor $processor)
	{
		$this->postProcessor = $processor;
	}

	/**
	 * Get the event dispatcher used by the connection.
	 *
	 * @return \Illuminate\Events\Dispatcher
	 */
	public function getEventDispatcher()
	{
		return $this->events;
	}

	/**
	 * Set the event dispatcher instance on the connection.
	 *
	 * @param  \Illuminate\Events\Dispatcher
	 */
	public function setEventDispatcher(Dispatcher $events)
	{
		$this->events = $events;
	}

}
