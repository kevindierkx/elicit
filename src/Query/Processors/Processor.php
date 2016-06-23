<?php namespace Kevindierkx\Elicit\Query\Processors;

use Kevindierkx\Elicit\Query\Builder;

class Processor
{
    /**
     * Process the results of an API request.
     *
     * @param  \Kevindierkx\Elicit\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    protected function processRequest(Builder $query, $results)
    {
        // Here we validate the results being returned to be associative.
        // When they are not we wrap them in an array making it easier for
        // elicit to them parse as a model.
        if (! empty($results) && array_keys($results) !== range(0, count($results) - 1)) {
            return [$results];
        }

        // Here we return the results directly, assuming the items in
        // the array are a collection.
        return $results;
    }

    /**
     * Process the results of both an "Index" and "Show" API request
     *
     * @param  \Kevindierkx\Elicit\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processShowRequest(Builder $query, $results)
    {
        return $this->processRequest($query, $results);
    }

    /**
     * Process the results of an "Create" API request
     *
     * @param  \Kevindierkx\Elicit\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processCreateRequest(Builder $query, $results)
    {
        return $this->processRequest($query, $results);
    }

    /**
     * Process the result of an "Update" API request
     *
     * @param  \Kevindierkx\Elicit\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processUpdateRequest(Builder $query, $results)
    {
        return $this->processRequest($query, $results);
    }

    /**
     * Process the result of an "Delete" API request
     *
     * @param  \Kevindierkx\Elicit\Query\Builder  $query
     * @param  array  $results
     * @return boolean
     */
    public function processDeleteRequest(Builder $query, $results)
    {
        // We assume that there isn't any response on
        // a successful delete operation (204 No Content)
        return ( count($results) != 0 );
    }
}
