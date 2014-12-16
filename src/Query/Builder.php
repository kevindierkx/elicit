<?php namespace Kevindierkx\Elicit\Query;

use Closure;
use Kevindierkx\Elicit\Connection\ConnectionInterface;
use Kevindierkx\Elicit\Query\Grammars\Grammar;
use Kevindierkx\Elicit\Query\Processors\Processor;

class Builder {

	/**
	 * The API connection instance.
	 *
	 * @var \Kevindierkx\Elicit\Connection\Connection
	 */
	protected $connection;

	/**
	 * The path which the query is targeting.
	 *
	 * @var array
	 */
	public $from;

	/**
	 * The query parameters for the request.
	 *
	 * @var array
	 */
	public $wheres;

	/**
	 * The post body for the request.
	 *
	 * @var array
	 */
	public $body;

	/**
	 * The maximum number of records to return.
	 *
	 * @var int
	 */
	public $limit;

	/**
	 * The API query grammar instance.
	 *
	 * @var \Kevindierkx\Elicit\Query\Grammars\Grammar
	 */
	protected $grammar;

	/**
	 * The database query post processor instance.
	 *
	 * @var \Kevindierkx\Elicit\Query\Processors\Processor
	 */
	protected $processor;

	/**
	 * Create a new reuqets builder instance.
	 *
	 * @param  \Kevindierkx\Elicit\Connection\ConnectionInterface  $connection
	 * @param  \Kevindierkx\Elicit\Query\Grammars\Grammar          $grammar
	 * @param  \Kevindierkx\Elicit\Query\Processors\Processor      $processor
	 */
	public function __construct(
		ConnectionInterface $connection,
		Grammar $grammar,
		Processor $processor
	)
	{
		$this->connection = $connection;

		$this->grammar = $grammar;

		$this->processor = $processor;
	}

	/**
	 * Execute a query for a single record by ID.
	 *
	 * @param  int    $id
	 * @param  array  $columns
	 * @return mixed|static
	 */
	public function find($id)
	{
		return $this->where('id', $id)->first();
	}

	/**
	 * Execute the query and get the first result.
	 *
	 * @return mixed|static
	 */
	public function first()
	{
		$results = $this->take(1)->get();

		return count($results) > 0 ? reset($results) : null;
	}

	/**
	 * Execute the query.
	 *
	 * @param  array  $columns
	 * @return array|static[]
	 */
	public function get()
	{
		return $this->processor->processRequest($this, $this->runRequest());
	}

	/**
	 * Run the query against the connection.
	 *
	 * @return array
	 */
	protected function runRequest()
	{
		return $this->connection->request($this->toRequest($this));
	}

	/**
	 * Set the path which the query is targeting.
	 *
	 * @param  array  $paths
	 * @return $this
	 */
	public function from(array $path)
	{
		$this->from = $path;

		return $this;
	}

	/**
	 * Add a basic where clause to the query.
	 *
	 * @param  string  $column
	 * @param  mixed   $value
	 * @return $this
	 */
	public function where($column, $value = null)
	{
		// If the column is an array, we will assume it is an array of key-value pairs
		// and can add them each as a where clause.
		if ( is_array($column) ) {
			return $this->whereNested(function($query) use ($column) {
				foreach ($column as $key => $value) {
					$query->where($key, $value);
				}
			});
		}

		$this->wheres[] = compact('column', 'value');

		return $this;
	}

	/**
	 * Add a nested where statement to the query.
	 *
	 * @param  \Closure $callback
	 * @return \Kevindierkx\Elicit\Query\Builder|static
	 */
	public function whereNested(Closure $callback)
	{
		// To handle nested queries we'll actually create a brand new query instance
		// and pass it off to the Closure that we have. The Closure can simply do
		// do whatever it wants to a query then we will store it for compiling.
		$query = $this->newQuery();

		call_user_func($callback, $query);

		return $this->addNestedWhereQuery($query);
	}

	/**
	 * Merge another query builder wheres with the query builder wheres.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder|static $query
	 * @return $this
	 */
	public function addNestedWhereQuery($query)
	{
		if ( count($query->wheres) ) {
			$wheres = $this->wheres ?: [];

			$this->wheres = array_merge($wheres, $query->wheres);
		}

		return $this;
	}

	/**
	 * Handles dynamic "where" clauses to the query.
	 *
	 * @param  string  $method
	 * @param  string  $parameters
	 * @return $this
	 */
	public function dynamicWhere($method, $parameters)
	{
		$finder   = substr($method, 5);
		$segments = preg_split('/(And)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);

		$index = 0;

		foreach ($segments as $segment) {
			// If the segment is not a boolean connector, we can assume it is a column's name
			// and we will add it to the query as a new constraint as a where clause, then
			// we can keep iterating through the dynamic method string's segments again.
			if ( $segment != 'And' ) {
				$this->addDynamic($segment, $parameters, $index);

				$index++;
			}
		}

		return $this;
	}

	/**
	 * Add a single dynamic where clause statement to the query.
	 *
	 * @param  string  $segment
	 * @param  array   $parameters
	 * @param  int     $index
	 * @return void
	 */
	protected function addDynamic($segment, $parameters, $index)
	{
		// Once we have parsed out the columns we are ready to add it to this
		// query as a where clause just like any other clause on the query.
		$this->where(snake_case($segment), $parameters[$index]);
	}

	/**
	 * Add a post field to the query.
	 *
	 * @param  string  $column
	 * @param  mixed   $value
	 * @return $this
	 */
	public function postField($column, $value = null)
	{
		// If the column is an array, we will assume it is an array of key-value pairs
		// and can add them each as a post field.
		if ( is_array($column) ) {
			return $this->postFieldNested(function($query) use ($column) {
				foreach ($column as $key => $value) {
					$query->postField($key, $value);
				}
			});
		}

		$this->body[] = compact('column', 'value');

		return $this;
	}

	/**
	 * Add a nested post field to the query.
	 *
	 * @param  \Closure $callback
	 * @return \Kevindierkx\Elicit\Query\Builder|static
	 */
	public function postFieldNested(Closure $callback)
	{
		// To handle nested post fields we'll actually create a brand new query instance
		// and pass it off to the Closure that we have. The Closure can simply do
		// do whatever it wants to a post field then we will store it for compiling.
		$query = $this->newQuery();

		call_user_func($callback, $query);

		return $this->addNestedPostFieldQuery($query);
	}

	/**
	 * Merge another query builder body with the query builder body.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder|static $query
	 * @return $this
	 */
	public function addNestedPostFieldQuery($query)
	{
		if ( count($query->body) ) {
			$body = $this->body ?: [];

			$this->body = array_merge($body, $query->body);
		}

		return $this;
	}

	/**
	 * Set the "limit" value of the query.
	 *
	 * @param  int  $value
	 * @return $this
	 */
	public function limit($value)
	{
		if ( $value > 0 ) $this->limit = $value;

		return $this;
	}

	/**
	 * Alias to set the "limit" value of the query.
	 *
	 * @param  int  $value
	 * @return \Kevindierkx\Elicit\Query\Builder|static
	 */
	public function take($value)
	{
		return $this->limit($value);
	}

	/**
	 * Get a new instance of the query builder.
	 *
	 * @return \Kevindierkx\Elicit\Query\Builder
	 */
	public function newQuery()
	{
		return new Builder($this->connection, $this->grammar, $this->processor);
	}

	/**
	 * Parse the request representation of the query.
	 *
	 * @return array
	 */
	public function toRequest()
	{
		return $this->grammar->compileRequest($this);
	}

	/**
	 * Get the database connection instance.
	 *
	 * @return \Kevindierkx\Elicit\ConnectionInterface
	 */
	public function getConnection()
	{
		return $this->connection;
	}

	/**
	 * Get the database query processor instance.
	 *
	 * @return \Kevindierkx\Elicit\Query\Processors\Processor
	 */
	public function getProcessor()
	{
		return $this->processor;
	}

	/**
	 * Get the query grammar instance.
	 *
	 * @return \Kevindierkx\Elicit\Query\Grammars\Grammar
	 */
	public function getGrammar()
	{
		return $this->grammar;
	}

	/**
	 * Handle dynamic method calls into the method.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 *
	 * @throws \BadMethodCallException
	 */
	public function __call($method, $parameters)
	{
		if ( starts_with($method, 'where') ) {
			return $this->dynamicWhere($method, $parameters);
		}

		$className = get_class($this);

		throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
	}

}
