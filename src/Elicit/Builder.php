<?php namespace Kevindierkx\Elicit\Elicit;

use Closure;
use Kevindierkx\Elicit\Query\Builder as QueryBuilder;

class Builder {

	/**
	 * The base query builder instance.
	 *
	 * @var \Kevindierkx\Elicit\Query\Builder
	 */
	protected $query;

	/**
	 * The model being queried.
	 *
	 * @var \PCextreme\AosManagerClient\Elicit\Model
	 */
	protected $model;

	/**
	 * The methods that should be returned from query builder.
	 *
	 * @var array
	 */
	protected $passthru = ['toRequest'];

	/**
	 * Create a new Eloquent query builder instance.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @return void
	 */
	public function __construct(QueryBuilder $query)
	{
		$this->query = $query;
	}

	/**
	 * Find a model by its primary key.
	 *
	 * @param  mixed  $id
	 * @return \Kevindierkx\Elicit\Elicit\Model|static|null
	 */
	public function find($id)
	{
		$this->query->where($this->model->getKeyName(), $id);

		return $this->first();
	}

	/**
	 * Find a model by its primary key or throw an exception.
	 *
	 * @param  mixed  $id
	 * @param  array  $columns
	 * @return \Kevindierkx\Elicit\Elicit\Model|static
	 *
	 * @throws \Kevindierkx\Elicit\Elicit\ModelNotFoundException
	 */
	public function findOrFail($id)
	{
		if ( ! is_null($model = $this->find($id)) ) return $model;

		throw (new ModelNotFoundException)->setModel(get_class($this->model));
	}

	/**
	 * Execute a "show" on the API and get the first result.
	 *
	 * @return \Kevindierkx\Elicit\Elicit\Model|static|null
	 */
	public function first()
	{
		$path = $this->model->getPath('show');

		$this->query->from($path);

		return $this->get()->first();
	}

	/**
	 * Execute a "show" on the API and get the first result or throw an exception.
	 *
	 * @return \Kevindierkx\Elicit\Elicit\Model|static
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	public function firstOrFail()
	{
		if ( ! is_null($model = $this->first()) ) return $model;

		throw (new ModelNotFoundException)->setModel(get_class($this->model));
	}

	/**
	 * Execute an "index" on the API.
	 *
	 * @return \Kevindierkx\Elicit\Elicit\Collection|static
	 */
	public function get()
	{
		$hasFrom = ! is_null($this->query->from);

		// When no from has been specified at this point the developer
		// tries to do a basic where query. In this case we want to use the index
		// path, since the index path is supposed to return collections.
		if ( ! $hasFrom ) {
			$path = $this->model->getPath('index');
			$this->query->from($path);
		}

		$models = $this->getModels();

		return $this->model->newCollection($models);
	}

	/**
	 * Execute a "delete" on the API.
	 *
	 * @return mixed
	 */
	public function delete()
	{
		$path = $this->model->getPath('destroy');

		$this->query->from($path);

		return $this->query->delete();
	}

	/**
	 * Get the hydrated models.
	 *
	 * @param  array  $columns
	 * @return \Kevindierkx\Elicit\Elicit\Model
	 */
	public function getModels()
	{
		// First, we will simply get the raw results from the query builders which we
		// can use to populate an array with Eloquent models. We will pass columns
		// that should be selected as well, which are typically just everything.
		$results = $this->query->get();

		$connection = $this->model->getConnectionName();

		$models = [];

		// Once we have the results, we can spin through them and instantiate a fresh
		// model instance for each records we retrieved from the database. We will
		// also set the proper connection name for the model after we create it.
		foreach ($results as $result) {
			$models[] = $model = $this->model->newFromBuilder($result);

			$model->setConnection($connection);
		}

		return $models;
	}

	/**
	 * Get the underlying query builder instance.
	 *
	 * @return \Kevindierkx\Elicit\Query\Builder|static
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * Set the underlying query builder instance.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @return void
	 */
	public function setQuery($query)
	{
		$this->query = $query;
	}

	/**
	 * Get the model instance being queried.
	 *
	 * @return \Kevindierkx\Elicit\Elicit\Model
	 */
	public function getModel()
	{
		return $this->model;
	}

	/**
	 * Set a model instance for the model being queried.
	 *
	 * @param  \Kevindierkx\Elicit\Elicit\Model  $model
	 * @return $this
	 */
	public function setModel(Model $model)
	{
		$this->model = $model;

		return $this;
	}

	/**
	 * Dynamically handle calls into the query instance.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		if ( method_exists($this->model, $scope = 'scope'.ucfirst($method)) ) {
			return $this->callScope($scope, $parameters);
		}

		$result = call_user_func_array(array($this->query, $method), $parameters);

		return in_array($method, $this->passthru) ? $result : $this;
	}

	/**
	 * Force a clone of the underlying query builder when cloning.
	 *
	 * @return void
	 */
	public function __clone()
	{
		$this->query = clone $this->query;
	}

}
