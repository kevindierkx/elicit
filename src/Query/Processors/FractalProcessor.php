<?php namespace Kevindierkx\Elicit\Query\Processors;

use Kevindierkx\Elicit\Query\Builder;

class FractalProcessor extends Processor {

	/**
	 * Process the results of both an "Index" and "Show" API request
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @param  array  $results
	 * @return array|null
	 */
	public function processShowRequest(Builder $query, $results)
	{
		$hasData = isset($results['data']);

		// When the request returns with multiple arrays and a data
		// array we will assume the data array is an associative array
		// of arrays. This mostly happens when requesting a collection.
		if ($hasData && count($results) > 1) {
			return $results['data'];
		}

		return $results;
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
		return $results;
	}

	/**
	 * Process the result of an "Update" API request
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @param  array  $results
	 * @return array|null
	 */
	public function processUpdateRequest(Builder $query, $results)
	{
		return $results;
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
		return ( count($results) == 0 );
	}

}
