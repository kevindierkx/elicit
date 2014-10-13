<?php namespace PCextreme\Api\Elicit;

use PCextreme\Api\Query\Builder as QueryBuilder;

class Builder {

	/**
	 * The base query builder instance.
	 *
	 * @var \PCextreme\Api\Query\Builder
	 */
	protected $query;

	/**
	 * The model being queried.
	 *
	 * @var \PCextreme\AosManagerClient\Elicit\Model
	 */
	protected $model;

	/**
	 * The relationships that should be eager loaded.
	 *
	 * @var array
	 */
	protected $eagerLoad = [];

	/**
	 * The methods that should be returned from query builder.
	 *
	 * @var array
	 */
	protected $passthru = ['exists'];

	/**
	 * Create a new Eloquent query builder instance.
	 *
	 * @param  \PCextreme\Api\Query\Builder  $query
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
	 * @return \PCextreme\Api\Elicit\Model|static|null
	 */
	public function find($id)
	{
		if (is_array($id)) {
			return $this->findMany($id);
		}

		$this->query->where($this->model->getKeyName(), $id);

		return $this->first();
	}

	/**
	 * Find a model by its primary key.
	 *
	 * @param  array  $id
	 * @param  array  $columns
	 * @return \PCextreme\Api\Elicit\Model|Collection|static
	 */
	// public function findMany($id)
	// {
	// 	throw new \RuntimeException("findMany in Elicit\Builder needs some work.");

	// 	if (empty($id)) {
	// 		return $this->model->newCollection();
	// 	}

	// 	$this->query->whereIn($this->model->getQualifiedKeyName(), $id);

	// 	return $this->get($columns);
	// }

	/**
	 * Find a model by its primary key or throw an exception.
	 *
	 * @param  mixed  $id
	 * @param  array  $columns
	 * @return \PCextreme\Api\Elicit\Model|static
	 *
	 * @throws \PCextreme\Api\Elicit\ModelNotFoundException
	 */
	public function findOrFail($id)
	{
		if (! is_null($model = $this->find($id))) return $model;

		throw (new ModelNotFoundException)->setModel(get_class($this->model));
	}

	/**
	 * Execute the query and get the first result.
	 *
	 * @return \PCextreme\Api\Elicit\Model|static|null
	 */
	public function first()
	{
		$path = $this->model->getPath('show');

		$this->query->from($path);

		return $this->take(1)->get()->first();
	}

	/**
	 * Execute the query and get the first result or throw an exception.
	 *
	 * @return \PCextreme\Api\Elicit\Model|static
	 *
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	public function firstOrFail()
	{
		if (! is_null($model = $this->first())) return $model;

		throw (new ModelNotFoundException)->setModel(get_class($this->model));
	}

	/**
	 * Execute the query as a "index|show" statement.
	 *
	 * @return \PCextreme\Api\Elicit\Collection|static[]
	 */
	public function get()
	{
		$models = $this->getModels();

		// ... Eager load relations

		return $this->model->newCollection($models);
	}

	/**
	 * Set the relationships that should be eager loaded.
	 *
	 * @param  mixed  $relations
	 * @return $this
	 */
	public function with($relations)
	{
		if (is_string($relations)) {
			$relations = func_get_args();
		}

		$eagers = $this->parseRelations($relations);

		$this->eagerLoad = array_merge($this->eagerLoad, $eagers);

		return $this;
	}

	/**
	 * Get the hydrated models without eager loading.
	 *
	 * @param  array  $columns
	 * @return \PCextreme\Api\Elicit\Model[]
	 */
	public function getModels()
	{
		// First, we will simply get the raw results from the query builders which we
		// can use to populate an array with Eloquent models. We will pass columns
		// that should be selected as well, which are typically just everything.
		$results = $this->query->get();

		$connection = $this->model->getConnectionName();

		$models = array();

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
	 * Parse a list of relations into individuals.
	 *
	 * @param  array  $relations
	 * @return array
	 */
	protected function parseRelations(array $relations)
	{
		$results = array();

		foreach ($relations as $name => $constraints) {
			// If the "relation" value is actually a numeric key, we can assume that no
			// constraints have been specified for the eager load and we'll just put
			// an empty Closure with the loader so that we can treat all the same.
			if (is_numeric($name)) {
				$f = function() {};

				list($name, $constraints) = array($constraints, $f);
			}

			// We need to separate out any nested includes. Which allows the developers
			// to load deep relationships using "dots" without stating each level of
			// the relationship with its own key in the array of eager load names.
			$results = $this->parseNested($name, $results);

			$results[$name] = $constraints;
		}

		return $results;
	}

	/**
	 * Get the underlying query builder instance.
	 *
	 * @return \PCextreme\Api\Query\Builder|static
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * Set the underlying query builder instance.
	 *
	 * @param  \PCextreme\Api\Query\Builder  $query
	 * @return void
	 */
	public function setQuery($query)
	{
		$this->query = $query;
	}

	/**
	 * Get the model instance being queried.
	 *
	 * @return \PCextreme\Api\Elicit\Model
	 */
	public function getModel()
	{
		return $this->model;
	}

	/**
	 * Set a model instance for the model being queried.
	 *
	 * @param  \PCextreme\Api\Elicit\Model  $model
	 * @return $this
	 */
	public function setModel(Model $model)
	{
		$this->model = $model;

		// $this->query->from($model->getTable());

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
		if (method_exists($this->model, $scope = 'scope'.ucfirst($method))) {
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
