<?php namespace PCextreme\Api\Query\Processors;

use PCextreme\Api\Query\Builder;

class FractalProcessor extends Processor {

	/**
	 * Process the results of an API request.
	 *
	 * @param  \PCextreme\Api\Query\Builder  $query
	 * @param  array  $results
	 * @return array
	 */
	public function processRequest(Builder $query, $results)
	{
		$hasData = isset($results['data']);

		// When the request returns with multiple arrays and a data
		// array we will assume the data array is an associative array
		// of arrays. This mostly happens when requesting a collection.
		if ($hasData && sizeof($results) > 1) {
			return $results['data'];
		}

		return $results;
	}

}