<?php

namespace Kevindierkx\Elicit\Connection;

use Closure;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Events\Dispatcher;
use Kevindierkx\Elicit\Connection\Exception\InvalidCredentialsException;
use Kevindierkx\Elicit\Connector\AbstractConnector;
use Kevindierkx\Elicit\Query\Grammars\Grammar;
use Kevindierkx\Elicit\Query\Processors\Processor;
use Kevindierkx\Elicit\QueryException;

abstract class AbstractConnection
{
    /**
     * The active connector instance.
     *
     * @var AbstractConnector
     */
    protected $connector;

    /**
     * The query grammar implementation.
     *
     * @var Grammar
     */
    protected $queryGrammar;

    /**
     * The query post processor implementation.
     *
     * @var Processor
     */
    protected $postProcessor;

    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Events\Dispatcher
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
    protected $config = [];

    /**
     * Create new API connection instance.
     *
     * @param  AbstractConnector  $connector
     * @param  array              $config
     */
    public function __construct(AbstractConnector $connector, array $config)
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
     * Get the default query grammar instance.
     *
     * @return Grammar
     */
    abstract public function getDefaultQueryGrammar();

    /**
     * Get the default post processor instance.
     *
     * @return Processor
     */
    abstract public function getDefaultPostProcessor();

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
     * Set the query post processor to the default implementation.
     *
     * @return void
     */
    public function useDefaultPostProcessor()
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Prepare request from query.
     *
     * TODO: This should be moved.
     */
    public function request(array $query)
    {
        return $this->run($query, function ($query) {
            $connector = $this->getConnector();

            $auth    = $this->getAuth($query);
            $method  = $this->getMethod($query);
            $url     = $this->getUrl($query);
            $options = $this->getOptions($query);

            if ($auth) {
                $token   = $connector->getAccessToken();
                $request = $connector->getAuthenticatedRequest($method, $url, $token, $options);
            } else {
                $request = $connector->getRequest($method, $url, $options);
            }

            $response = $connector->getResponse($request);

            return $response;
        });
    }

    // TODO: Replace with query object directly calling the grammar.
    private function getAuth(array $query)
    {
        return $query['from']['auth'];
    }

    // TODO: Replace with query object directly calling the grammar.
    private function getMethod(array $query)
    {
        return $query['from']['method'];
    }

    // TODO: Replace with query object directly calling the grammar.
    private function getUrl(array $query)
    {
        $url    = rtrim($this->getConnector()->getBaseDomain(), '/');
        $path   = isset($query['from']['path']) && ! is_null($query['from']['path']) ? '/'.ltrim($query['from']['path'], '/') : null;
        $wheres = isset($query['wheres']) && ! empty($query['wheres']) ? '?'.ltrim($query['wheres'], '?') : null;

        return $url.$path.$wheres;
    }

    // TODO: Replace with query object directly calling the grammar.
    private function getOptions(array $query)
    {
        $body = isset($query['body']) ? $query['body'] : null;

        return [
            'body' => $body,
        ];
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  array    $query
     * @param  Closure  $callback
     * @return array
     */
    protected function run(array $query, Closure $callback)
    {
        $start = microtime(true);

        $result = $callback($query);

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
     * @param  array    $query
     * @param  Closure  $callback
     * @return array
     * @throws InvalidCredentialsException
     * @throws QueryException
     *
     * @throws \GuzzleHttp\Exception\RequestException
     * @throws \GuzzleHttp\Exception\ClientException  400 Errors
     * @throws \GuzzleHttp\Exception\TooManyRedirectsException
     */
    // protected function runQueryCallback(array $query, Closure $callback)
    // {
    //     try {
    //         $result = $callback($query);
    //     } catch (ClientException $e) {
    //         $statusCode = $e->getResponse()->getStatusCode();
    //
    //         switch ($statusCode) {
    //             // When we receive a 401 status code from the API well assume
    //             // we are not authorized to see the resource. When using OAuth
    //             // we could try to fetch a new token from the API before stopping.
    //             case '401':
    //                 throw (new InvalidCredentialsException)->setConnection($this->config['name']);
    //
    //             // When we receive a 404 status code from the API well assume
    //             // the resource was not found. Returning an empty array here
    //             // will make it possible to use the findOrFail from the Elicit model.
    //             case '404':
    //                 return array();
    //
    //             default:
    //                 throw $e;
    //         }
    //     } catch (ServerException $e) {
    //         throw new QueryException($query, $e);
    //     }
    //
    //     return $result;
    // }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string    $query
     * @param  int|null  $time
     * @return void
     */
    public function logQuery($query, $time = null)
    {
        if (isset($this->events)) {
            $this->events->fire('elicit.query', [$query, $time, $this->getName()]);
        }

        if (! $this->loggingQueries) {
            return;
        }

        $this->queryLog[] = compact('query', 'time');
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param  int  $start
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get the current API connector.
     *
     * @return AbstractConnector
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * Set the API connector.
     *
     * @param  AbstractConnector  $connector
     * @return self
     */
    public function setConnector(Connector $connector)
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
     * @return Grammar
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * Set the query grammar used by the connection.
     *
     * @param  Grammar  $grammar
     * @return self
     */
    public function setQueryGrammar(Grammar $grammar)
    {
        $this->queryGrammar = $grammar;

        return $this;
    }

    /**
     * Get the query post processor used by the connection.
     *
     * @return Processor
     */
    public function getPostProcessor()
    {
        return $this->postProcessor;
    }

    /**
     * Set the query post processor used by the connection.
     *
     * @param  Processor  $processor
     * @return self
     */
    public function setPostProcessor(Processor $processor)
    {
        $this->postProcessor = $processor;

        return $this;
    }

    /**
     * Get the event dispatcher used by the connection.
     *
     * @return Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @param  Dispatcher  $events
     * @return self
     */
    public function setEventDispatcher(Dispatcher $events)
    {
        $this->events = $events;

        return $this;
    }
}
