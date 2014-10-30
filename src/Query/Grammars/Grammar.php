<?php namespace Kevindierkx\Elicit\Query\Grammars;

use Kevindierkx\Elicit\Query\Builder;

class Grammar extends AbstractGrammar {

	/**
	 * The components that make up a request.
	 *
	 * @var array
	 */
	protected $requestComponents = array(
		'from',
		'wheres',
	);

	/**
	 * Compile the query for the request.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder
	 * @return array
	 */
	public function compileRequest(Builder $query)
	{
		return $this->compileComponents($query);
	}

	/**
	 * Compile the components necessary for the request.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder
	 * @return array
	 */
	protected function compileComponents(Builder $query)
	{
		$request = array();

		foreach ($this->requestComponents as $component) {
			// To compile the query, we'll spin through each component of the query and
			// see if that component exists. If it does we'll just call the compiler
			// function for the component which is responsible for making the request.
			if (! is_null($query->$component)) {
				$method = 'compile'.ucfirst($component);

				$request[$component] = $this->$method($query, $query->$component);
			}
		}

		return $request;
	}

	/**
	 * Compile the "from" portion of the query.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @param  array  $from
	 * @return string
	 */
	protected function compileFrom(Builder $query, array $from)
	{
		$method = $from['method'];
		$path   = $from['path'];

		$hasNamedParameters = $this->hasNamedParameters($path);

		if ($hasNamedParameters) {
			$path = $this->replaceNamedParameters($path, $query->wheres);
		}

		return compact('method', 'path');
	}

	/**
	 * Compile the "where" portions of the query.
	 *
	 * @param  \Kevindierkx\Elicit\Query\Builder  $query
	 * @return string
	 */
	protected function compileWheres(Builder $query)
	{
		$request = array();

		$hasWheres = ! is_null($query->wheres);

		if ($hasWheres) {
			foreach ($query->wheres as $where) {
				if ($this->hasReplacedWhere($where)) continue;

				$request[$where['column']] = $where['value'];
			}

			if (count($request) > 0) {
				return http_build_query($request, false, '&', PHP_QUERY_RFC3986);
			}
		}

		return '';
	}

}
