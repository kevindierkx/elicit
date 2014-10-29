<?php namespace Kevindierkx\Elicit\Elicit;

use ArrayAccess;
use JsonSerializable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Events\Dispatcher;

use Kevindierkx\Elicit\Query\Builder as QueryBuilder;
use Kevindierkx\Elicit\Connection\Connection;
use Kevindierkx\Elicit\ConnectionResolverInterface as Resolver;

abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable {

	/**
	 * The connection name for the model.
	 *
	 * @var string
	 */
	protected $connection;

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'id';

	/**
	 * The paths used during requests.
	 *
	 * @var array
	 */
	protected $paths = array();

	/**
	 * Default configurations for paths.
	 *
	 * @var array
	 */
	protected $defaults = [
		'index'   => ['method' => Connection::METHOD_GET],
		'show'    => ['method' => Connection::METHOD_GET],
		'store'   => ['method' => Connection::METHOD_POST],
		'update'  => ['method' => Connection::METHOD_PUT],
		'destroy' => ['method' => Connection::METHOD_DELETE],
		'options' => ['method' => Connection::METHOD_OPTIONS],
	];

	/**
	 * The attributes that should be hidden for arrays.
	 *
	 * @var array
	 */
	protected $hidden = array();

	/**
	 * The attributes that should be visible in arrays.
	 *
	 * @var array
	 */
	protected $visible = array();

	/**
	 * The model's attributes.
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * The model attribute's original state.
	 *
	 * @var array
	 */
	protected $original = array();

	/**
	 * The relations to eager load on every query.
	 *
	 * @var array
	 */
	protected $with = array();

	/**
	 * Indicates if the model exists.
	 *
	 * @var bool
	 */
	public $exists = false;

	/**
	 * Indicates whether attributes are snake cased on arrays.
	 *
	 * @var bool
	 */
	public static $snakeAttributes = true;

	/**
	 * The connection resolver instance.
	 *
	 * @var \Kevindierkx\Elicit\ConnectionResolverInterface
	 */
	protected static $resolver;

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Illuminate\Contracts\Events\Dispatcher
	 */
	protected static $dispatcher;

	/**
	 * The array of booted models.
	 *
	 * @var array
	 */
	protected static $booted = array();

	/**
	 * The cache of the mutated attributes for each class.
	 *
	 * @var array
	 */
	protected static $mutatorCache = array();

	/**
	 * Create a new API model instance.
	 *
	 * @param  array  $attributes
	 * @return void
	 */
	public function __construct(array $attributes = array())
	{
		$this->bootIfNotBooted();

		$this->syncOriginal();

		$this->fill($attributes);
	}

	/**
	 * Check if the model needs to be booted and if so, do it.
	 *
	 * @return void
	 */
	protected function bootIfNotBooted()
	{
		$class = get_class($this);

		if (! isset(static::$booted[$class])) {
			static::$booted[$class] = true;

			$this->fireModelEvent('booting', false);

			static::boot();

			$this->fireModelEvent('booted', false);
		}
	}

	/**
	 * The "booting" method of the model.
	 *
	 * @return void
	 */
	protected static function boot()
	{
		$class = get_called_class();

		static::$mutatorCache[$class] = array();

		// Here we will extract all of the mutated attributes so that we can quickly
		// spin through them after we export models to their array form, which we
		// need to be fast. This will let us always know the attributes mutate.
		foreach (get_class_methods($class) as $method) {
			if (preg_match('/^get(.+)Attribute$/', $method, $matches)) {
				if (static::$snakeAttributes) {
					$matches[1] = snake_case($matches[1]);
				}

				static::$mutatorCache[$class][] = lcfirst($matches[1]);
			}
		}

		static::bootTraits();
	}

	/**
	 * Boot all of the bootable traits on the model.
	 *
	 * @return void
	 */
	protected static function bootTraits()
	{
		foreach (class_uses_recursive(get_called_class()) as $trait) {
			if (method_exists(get_called_class(), $method = 'boot'.class_basename($trait))) {
				forward_static_call([get_called_class(), $method]);
			}
		}
	}

	/**
	 * Fill the model with an array of attributes.
	 *
	 * @param  array  $attributes
	 * @return $this
	 */
	public function fill(array $attributes)
	{
		foreach ($attributes as $key => $value) {
			$this->setAttribute($key, $value);
		}

		return $this;
	}

	/**
	 * Being querying a model with eager loading.
	 *
	 * @param  array|string  $relations
	 * @return \Kevindierkx\Elicit\Elicit\Builder|static
	 */
	public static function with($relations)
	{
		if (is_string($relations)) {
			$relations = func_get_args();
		}

		$instance = new static;

		return $instance->newQuery()->with($relations);
	}

	/**
	 * Begin querying the model.
	 *
	 * @return \Kevindierkx\Elicit\elicit\Builder
	 */
	public static function query()
	{
		return (new static)->newQuery();
	}

	/**
	 * Get all of the models from the database.
	 *
	 * @param  array  $columns
	 * @return \Kevindierkx\Elicit\Elicit\Collection|static[]
	 */
	public static function all()
	{
		$instance = new static;

		$path = $instance->getPath('index');

		return $instance->newQuery()->from($path)->get();
	}

	/**
	 * Find a model by its primary key.
	 *
	 * @param  mixed  $id
	 * @return \Illuminate\Support\Collection|static
	 */
	public static function find($id)
	{
		if (is_array($id) && empty($id)) return new Collection;

		$instance = new static;

		return $instance->newQuery()->find($id);
	}

	/**
	 * Find a model by its primary key or return new static.
	 *
	 * @param  mixed  $id
	 * @return \Illuminate\Support\Collection|static
	 */
	public static function findOrNew($id)
	{
		if (! is_null($model = static::find($id))) return $model;

		return new static;
	}

	/**
	 * Find a model by its primary key or throw an exception.
	 *
	 * @param  mixed  $id
	 * @return \Illuminate\Support\Collection|static
	 *
	 * @throws \Kevindierkx\Elicit\Elicit\ModelNotFoundException
	 */
	public static function findOrFail($id)
	{
		if (! is_null($model = static::find($id))) return $model;

		throw (new ModelNotFoundException)->setModel(get_called_class());
	}

	/**
	 * Create a new instance of the given model.
	 *
	 * @param  array  $attributes
	 * @param  bool   $exists
	 * @return static
	 */
	public function newInstance($attributes = array(), $exists = false)
	{
		// This method just provides a convenient way for us to generate fresh model
		// instances of this current model. It is particularly useful during the
		// hydration of new objects via the Eloquent query builder instances.
		$model = new static((array) $attributes);

		$model->exists = $exists;

		return $model;
	}

	/**
	 * Create a new model instance that is existing.
	 *
	 * @param  array  $attributes
	 * @return static
	 */
	public function newFromBuilder($attributes = array())
	{
		$instance = $this->newInstance(array(), true);

		$instance->setRawAttributes((array) $attributes, true);

		return $instance;
	}

	/**
	 * Get a new query builder for the model's API.
	 *
	 * @return \Kevindierkx\Elicit\Elicit\Builder
	 */
	public function newQuery()
	{
		$builder = $this->newElicitBuilder(
			$this->newBaseQueryBuilder()
		);

		// Once we have the query builders, we will set the model instances so the
		// builder can easily access any information it may need from the model
		// while it is constructing and executing various queries against it.
		$builder->setModel($this)->with($this->with);

		return $builder;
	}

	/**
	 * Create a new Elicit query builder for the model.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @return \Kevindierkx\Elicit\Builder|static
	 */
	public function newElicitBuilder($query)
	{
		return new Builder($query);
	}

	/**
	 * Get a new query builder instance for the connection.
	 *
	 * @return \Kevindierkx\Elicit\Query\Builder
	 */
	protected function newBaseQueryBuilder()
	{
		$conn = $this->getConnection();

		return new QueryBuilder($conn, $conn->getPostProcessor());
	}

	/**
	 * Create a new Elicit Collection instance.
	 *
	 * @param  array  $models
	 * @return \Kevindierkx\Elicit\Elicit\Collection
	 */
	public function newCollection(array $models = array())
	{
		return new Collection($models);
	}

	/**
	 * Get the value of the model's primary key.
	 *
	 * @return mixed
	 */
	public function getKey()
	{
		return $this->getAttribute($this->getKeyName());
	}

	/**
	 * Get the primary key for the model.
	 *
	 * @return string
	 */
	public function getKeyName()
	{
		return $this->primaryKey;
	}

	/**
	 * Set the primary key for the model.
	 *
	 * @return void
	 */
	public function setKeyName($key)
	{
		$this->primaryKey = $key;
	}

	/**
	 * Check if a path has been registered.
	 *
	 * @param  string  $key
	 * @return boolean
	 */
	public function hasPath($key)
	{
		return isset($this->paths[$key]);
	}

	/**
	 * Get a path attribute from the model.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function getPath($key)
	{
		$paths = $this->getMergedPaths();

		if (array_key_exists($key, $paths)) {
			return $paths[$key];
		}
	}

	/**
	 * Set a path attribute for the model.
	 *
	 * @param string  $path
	 * @param string  $value
	 * @param mixed   $method
	 */
	public function setPath($path, $value, $method = null)
	{
		$this->paths[$path] = ['path' => $value];

		if (! is_null($method)) {
			$this->paths[$path]['method'] = $method;
		}
	}

	/**
	 * Get a path value from the model.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function getPathValue($key)
	{
		$hasPath = isset($paths[$key]['method']);

		if ($hasPath) {
			return $this->paths[$key]['path'];
		}
	}

	/**
	 * Get a path method from the model.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function getPathMethod($key)
	{
		$paths = $this->getMergedPaths();

		$hasPath = array_key_exists($key, $paths);
		$hasMethod = isset($paths[$key]['method']);

		if ($hasPath && $hasMethod) {
			return $paths[$key]['method'];
		}
	}

	/**
	 * Get the paths for the model merged with the path defaults.
	 *
	 * @return mixed
	 */
	public function getMergedPaths()
	{
		$paths = [];

		foreach ($this->paths as $key => $value) {
			$paths[$key] = array_merge($this->defaults[$key], $value);
		}

		return $paths;
	}

	/**
	 * Get the hidden attributes for the model.
	 *
	 * @return array
	 */
	public function getHidden()
	{
		return $this->hidden;
	}

	/**
	 * Set the hidden attributes for the model.
	 *
	 * @param  array  $hidden
	 * @return void
	 */
	public function setHidden(array $hidden)
	{
		$this->hidden = $hidden;
	}

	/**
	 * Get the visible attributes for the model.
	 *
	 * @return array
	 */
	public function getVisible()
	{
		return $this->visible;
	}

	/**
	 * Set the visible attributes for the model.
	 *
	 * @param  array  $visible
	 * @return void
	 */
	public function setVisible(array $visible)
	{
		$this->visible = $visible;
	}

	/**
	 * Get the current connection name for the model.
	 *
	 * @return string
	 */
	public function getConnectionName()
	{
		return $this->connection;
	}

	/**
	 * Get the database connection for the model.
	 *
	 * @return \Illuminate\Database\Connection
	 */
	public function getConnection()
	{
		return static::resolveConnection($this->connection);
	}

	/**
	 * Set the connection associated with the model.
	 *
	 * @param  string  $name
	 * @return $this
	 */
	public function setConnection($name)
	{
		$this->connection = $name;

		return $this;
	}

	/**
	 * Resolve a connection instance.
	 *
	 * @param  string  $connection
	 * @return \Illuminate\Database\Connection
	 */
	public static function resolveConnection($connection = null)
	{
		return static::$resolver->connection($connection);
	}

	/**
	 * Get the connection resolver instance.
	 *
	 * @return \Illuminate\Database\ConnectionResolverInterface
	 */
	public static function getConnectionResolver()
	{
		return static::$resolver;
	}

	/**
	 * Set the connection resolver instance.
	 *
	 * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
	 * @return void
	 */
	public static function setConnectionResolver(Resolver $resolver)
	{
		static::$resolver = $resolver;
	}

	/**
	 * Unset the connection resolver for models.
	 *
	 * @return void
	 */
	public static function unsetConnectionResolver()
	{
		static::$resolver = null;
	}

	/**
	 * Get the event dispatcher instance.
	 *
	 * @return \Illuminate\Contracts\Events\Dispatcher
	 */
	public static function getEventDispatcher()
	{
		return static::$dispatcher;
	}

	/**
	 * Set the event dispatcher instance.
	 *
	 * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
	 * @return void
	 */
	public static function setEventDispatcher(Dispatcher $dispatcher)
	{
		static::$dispatcher = $dispatcher;
	}

	/**
	 * Unset the event dispatcher for models.
	 *
	 * @return void
	 */
	public static function unsetEventDispatcher()
	{
		static::$dispatcher = null;
	}

	/**
	 * Get all of the current attributes on the model.
	 *
	 * @return array
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * Set the array of model attributes. No checking is done.
	 *
	 * @param  array  $attributes
	 * @param  bool   $sync
	 * @return void
	 */
	public function setRawAttributes(array $attributes, $sync = false)
	{
		$this->attributes = $attributes;

		if ($sync) $this->syncOriginal();
	}

	/**
	 * Get the model's original attribute values.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return array
	 */
	public function getOriginal($key = null, $default = null)
	{
		return array_get($this->original, $key, $default);
	}

	/**
	 * Sync the original attributes with the current.
	 *
	 * @return $this
	 */
	public function syncOriginal()
	{
		$this->original = $this->attributes;

		return $this;
	}

	/**
	 * Sync a single original attribute with its current value.
	 *
	 * @param  string  $attribute
	 * @return $this
	 */
	public function syncOriginalAttribute($attribute)
	{
		$this->original[$attribute] = $this->attributes[$attribute];

		return $this;
	}

	/**
	 * Set a given attribute on the model.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function setAttribute($key, $value)
	{
		$this->attributes[$key] = $value;
	}

	/**
	 * Get an attribute from the model.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function getAttribute($key)
	{
		$inAttributes = array_key_exists($key, $this->attributes);

		// If the key references an attribute, we can just go ahead and return the
		// plain attribute value from the model. This allows every attribute to
		// be dynamically accessed through the _get method without accessors.
		if ($inAttributes || $this->hasGetMutator($key)) {
			return $this->getAttributeValue($key);
		}

		$inPaths = array_key_exists($key, $this->paths);

		// If the key references an path attribute, we can just go ahead and return the
		// plain attribute value from the model.
		if ($inPaths) {
			return $this->getPathValue($key);
		}

		// If the key already exists in the relationships array, it just means the
		// relationship has already been loaded, so we'll just return it out of
		// here because there is no need to query within the relations twice.
		if (array_key_exists($key, $this->relations)) {
			return $this->relations[$key];
		}

		// If the "attribute" exists as a method on the model, we will just assume
		// it is a relationship and will load and return results from the query
		// and hydrate the relationship's value on the "relationships" array.
		$camelKey = camel_case($key);

		if (method_exists($this, $camelKey)) {
			return $this->getRelationshipFromMethod($key, $camelKey);
		}
	}

	/**
	 * Get a plain attribute (not a relationship).
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	protected function getAttributeValue($key)
	{
		$value = $this->getAttributeFromArray($key);

		return $value;
	}

	/**
	 * Get an attribute from the $attributes array.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	protected function getAttributeFromArray($key)
	{
		if (array_key_exists($key, $this->attributes)) {
			return $this->attributes[$key];
		}
	}

	/**
	 * Get an attribute array of all arrayable attributes.
	 *
	 * @return array
	 */
	protected function getArrayableAttributes()
	{
		return $this->getArrayableItems($this->attributes);
	}

	/**
	 * Convert the model's attributes to an array.
	 *
	 * @return array
	 */
	public function attributesToArray()
	{
		$attributes = $this->getArrayableAttributes();

		return $attributes;
	}

	/**
	 * Fire the given event for the model.
	 *
	 * @param  string  $event
	 * @param  bool    $halt
	 * @return mixed
	 */
	protected function fireModelEvent($event, $halt = true)
	{
		if (! isset(static::$dispatcher)) return true;

		// We will append the names of the class to the event to distinguish it from
		// other model events that are fired, allowing us to listen on each model
		// event set individually instead of catching event for all the models.
		$event = "elicit.{$event}: ".get_class($this);

		$method = $halt ? 'until' : 'fire';

		return static::$dispatcher->$method($event, $this);
	}

	/**
	 * Dynamically retrieve attributes on the model.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->getAttribute($key);
	}

	/**
	 * Dynamically set attributes on the model.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function __set($key, $value)
	{
		$this->setAttribute($key, $value);
	}

	/**
	 * Determine if the given attribute exists.
	 *
	 * @param  mixed  $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return isset($this->$offset);
	}

	/**
	 * Get the value for a given offset.
	 *
	 * @param  mixed  $offset
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->$offset;
	}

	/**
	 * Set the value for a given offset.
	 *
	 * @param  mixed  $offset
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->$offset = $value;
	}

	/**
	 * Unset the value for a given offset.
	 *
	 * @param  mixed  $offset
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		unset($this->$offset);
	}

	/**
	 * Convert the model instance to JSON.
	 *
	 * @param  int  $options
	 * @return string
	 */
	public function toJson($options = 0)
	{
		return json_encode($this->toArray(), $options);
	}

	/**
	 * Convert the object into something JSON serializable.
	 *
	 * @return array
	 */
	public function jsonSerialize()
	{
		return $this->toArray();
	}

	/**
	 * Convert the model instance to an array.
	 *
	 * @return array
	 */
	public function toArray()
	{
		$attributes = $this->attributesToArray();

		return $attributes;
	}

	/**
	 * Get an attribute array of all arrayable values.
	 *
	 * @param  array  $values
	 * @return array
	 */
	protected function getArrayableItems(array $values)
	{
		if (count($this->visible) > 0) {
			return array_intersect_key($values, array_flip($this->visible));
		}

		return array_diff_key($values, array_flip($this->hidden));
	}

	/**
	 * Handle dynamic method calls into the method.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		$query = $this->newQuery();

		return call_user_func_array(array($query, $method), $parameters);
	}

	/**
	 * Handle dynamic static method calls into the method.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public static function __callStatic($method, $parameters)
	{
		$instance = new static;

		return call_user_func_array(array($instance, $method), $parameters);
	}

	/**
	 * Convert the model to its string representation.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->toJson();
	}

}
