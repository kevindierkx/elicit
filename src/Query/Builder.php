<?php namespace PCextreme\Api\Query;

use PCextreme\Api\Connection\ConnectionInterface;
use PCextreme\Api\Query\Processors\Processor;

class Builder {

	/**
	 * The API connection instance.
	 *
	 * @var \PCextreme\Api\Connection\Connection
	 */
	protected $connection;

	/**
	 * The path which the query is targeting.
	 *
	 * @var array
	 */
	public $from = array();

	/**
	 * The query parameters for the request.
	 *
	 * @var array
	 */
	public $wheres = array();

	/**
	 * The maximum number of records to return.
	 *
	 * @var int
	 */
	public $limit;

	/**
	 * The database query post processor instance.
	 *
	 * @var \PCextreme\Api\Query\Processors\Processor
	 */
	protected $processor;

	/**
	 * Create a new reuqets builder instance.
	 *
	 * @param  \PCextreme\Api\Connection\ConnectionInterface  $connection
	 * @param  \PCextreme\Api\Query\Processors\Processor      $processor
	 */
	public function __construct(
		ConnectionInterface $connection,
		Processor $processor
	)
	{
		$this->connection = $connection;
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
	 * Execute the query as a "index|show" statement.
	 *
	 * @param  array  $columns
	 * @return array|static[]
	 */
	public function get()
	{
		// TODO: ... We could add some caching here.

		return $this->getFresh();
	}

	/**
	 * Execute the query as a fresh "index|show" statement.
	 *
	 * @return array|static[]
	 */
	public function getFresh()
	{
		return $this->processor->processRequest($this, $this->runRequest());
	}

	/**
	 * Run the query statement against the connection.
	 *
	 * @return array
	 */
	protected function runRequest()
	{
		$hasPath   = isset($this->from['path']);

		$hasMethod = isset($this->from['method']);

		// When no path or method has been provided the developer tries to run
		// a request using the query builder or is using a custom path. In this
		// case the request path and method need to be provided before running a request.
		if (! $hasPath || ! $hasMethod) {
			throw new \RuntimeException(
				"The request path and method need to be provided before running a request."
			);
		}

		$path   = $this->from['path'];

		$method = $this->from['method'];

		switch ($method) {
			case 'GET':
				return $this->connection->get($path, $this->wheres);
			case 'POST':
				return 'post';
			case 'PUT':
				return 'put';
			case 'PATCH':
				return 'patch';
			case 'DELETE':
				return 'delete';
			case 'OPTIONS':
				return 'options';
		}

		throw new \InvalidArgumentException("Unsupported request method [$method]");
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
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return $this
	 */
	public function where($key, $value = null)
	{
		// TODO: Add support for arrays.

		// If the column is an array, we will assume it is an array of key-value pairs
		// and can add them each as a where clause. We will maintain the boolean we
		// received when the method was called and pass it into the nested where.
		// if (is_array($column)) {
		// 	return $this->whereNested(function($query) use ($column) {
		// 		foreach ($column as $key => $value) {
		// 			$query->where($key, '=', $value);
		// 		}
		// 	}, $boolean);
		// }

		$this->wheres[$key] = $value;

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
	 * @return \PCextreme\Api\Query\Builder|static
	 */
	public function take($value)
	{
		return $this->limit($value);
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
