<?php namespace Kevindierkx\Elicit\Query;

use Closure;
use Kevindierkx\Elicit\Connection\AbstractConnection;
use Kevindierkx\Elicit\Query\Grammars\Grammar;
use Kevindierkx\Elicit\Query\Processors\Processor;

class Builder
{
    /**
     * The connection instance.
     *
     * @var AbstractConnection
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
     * The query grammar instance.
     *
     * @var Grammar
     */
    protected $grammar;

    /**
     * The query post processor instance.
     *
     * @var Processor
     */
    protected $processor;

    /**
     * Create a new request builder instance.
     *
     * @param  AbstractConnection  $connection
     * @param  Grammar             $grammar
     * @param  Processor           $processor
     */
    public function __construct(
        AbstractConnection $connection,
        Grammar $grammar,
        Processor $processor
    ) {
        $this->connection = $connection;

        $this->grammar = $grammar;

        $this->processor = $processor;
    }

    /**
     * Find a model by its ID.
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
     * Execute a "show" on the API and get the first result.
     *
     * @return mixed|static
     */
    public function first()
    {
        $results = $this->get();

        return count($results) > 0 ? reset($results) : null;
    }

    /**
     * Execute an "index" on the API.
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function get()
    {
        return $this->processor->processShowRequest($this, $this->runRequest());
    }

    /**
     * Execute an "create" on the API.
     * @return array|static[]
     */
    public function create()
    {
        return $this->processor->processCreateRequest($this, $this->runRequest());
    }

    /**
     * Execute an "update" on the API.
     *
     * @return array|static[]
     */
    public function update()
    {
        return $this->processor->processUpdateRequest($this, $this->runRequest());
    }

    /**
     * Execute a "delete" on the API.
     *
     * @param  mixed  $id
     * @return boolean
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check
        // the ID to allow developers to simply and quickly remove a single item
        // without manually specifying the where clauses.
        if (! is_null($id)) {
            $this->where('id', '=', $id);
        }

        return $this->processor->processDeleteRequest($this, $this->runRequest());
    }

    /**
     * Run the query against the connection.
     *
     * @return array
     */
    protected function runRequest()
    {
        return $this->connection->request($this->grammar->compileRequest($this));
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
            return $this->whereNested(function ($query) use ($column) {
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
        if (count($query->wheres)) {
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
            if ($segment != 'And') {
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
        if (is_array($column)) {
            return $this->postFieldNested(function ($query) use ($column) {
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
        if (count($query->body)) {
            $body = $this->body ?: [];

            $this->body = array_merge($body, $query->body);
        }

        return $this;
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
     * Get the connection instance.
     *
     * @return AbstractConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the query processor instance.
     *
     * @return Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Get the query grammar instance.
     *
     * @return Grammar
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
