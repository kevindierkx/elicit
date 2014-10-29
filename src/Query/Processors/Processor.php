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
		return $results;
	}

}