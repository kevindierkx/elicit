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
	 * The maximum number of records to return.
	 *
	 * @var int
	 */
	public $limit;

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
		if (is_array($column)) {
			return $this->whereNested(function($query) use ($column) {
				foreach ($column as $key => $value) {
					$query->where($key, $value);
				}
			});
		}

		// For simple wheres we just add them to the array.
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

		// The nested query does not have the from field set. At this point we don't
		// have the required information about the from field to set it. This is
		// however not a problem since all requests go to the same endpoint.

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
		if (count($query->wheres)) {
			$wheres = $this->wheres ?: array();

			$this->wheres = array_merge($wheres, $query->wheres);
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
		if ($value > 0) $this->limit = $value;

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
		if (starts_with($method, 'where')) {
			return $this->dynamicWhere($method, $parameters);
		}

		$className = get_class($this);

		throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
	}

}
