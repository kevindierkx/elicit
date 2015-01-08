<?php namespace Kevindierkx\Elicit\Query\Processors;

use Kevindierkx\Elicit\Query\Builder;

class Processor {

	/**
	 * Process the results of an API request.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @param  array  $results
	 * @return array
	 */
	public function processRequest(Builder $query, $results)
	{
		// Here we validate the results being returned to be associative.
		// When they are not we wrap them in an array making it easier for
		// elicit to them parse as a model.
		if (array_keys($results) !== range(0, count($results) - 1)) return [$results];

		// Here we return the results directly, assuming the items in
		// the array are a collection.
		return $results;
	}

}