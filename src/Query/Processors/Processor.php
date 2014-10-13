<?php namespace PCextreme\Api\Query\Processors;

use PCextreme\Api\Query\Builder;

class Processor {

	/**
	 * Process the results of an API request.
	 *
	 * @param  \PCextreme\Api\Query\Builder  $query
	 * @param  array  $results
	 * @return array
	 */
	public function processRequest(Builder $query, $results)
	{
		return $results;
	}

}