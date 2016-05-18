<?php

namespace Kevindierkx\Elicit\Query\Grammars;

use Kevindierkx\Elicit\Query\Builder;

class Grammar extends AbstractGrammar
{
    /**
     * The components that make up a request.
     *
     * @var array
     */
    protected $requestComponents = array(
        'from',
        'wheres',
        'body',
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
        $request = [];

        foreach ($this->requestComponents as $component) {
            // To compile the query, we'll spin through each component of the query and
            // see if that component exists. If it does we'll just call the compiler
            // function for the component which is responsible for making the request.
            if (!is_null($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $request[$component] = $this->$method($query, $query->$component);
            }
        }

        return $request;
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Kevindierkx\Elicit\Query\Builder $query
     * @param  array $from
     * @return string
     */
    protected function compileFrom(Builder $query, array $from)
    {
        $method = $from['method'];
        $path   = $from['path'];

        $hasNamedParameters = $this->hasNamedParameters($path);

        if ($hasNamedParameters) {
            // When there are no wheres provided we will provide an empty array
            // to the replace method. The replace method is able to provide a more
            // specific error message when a named parameter is missing.
            $wheres = $query->wheres ?: [];

            $path = $this->replaceNamedParameters($path, $wheres);
        }

        return compact('method', 'path');
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \Kevindierkx\Elicit\Query\Builder $query
     * @return string
     */
    protected function compileWheres(Builder $query)
    {
        $wheres = [];

        $hasWheres = !is_null($query->wheres);

        if ($hasWheres) {
            foreach ($query->wheres as $where) {
                if ($this->hasReplacedParameter($where)) {
                    continue;
                }

                $wheres[$where['column']] = $where['value'];
            }

            // Here we create the query string. Some APIs (Graphite) support multiple
            // parameters with the same key. When we find an entry with multiple values
            // well add it multiple times to the query.
            if (count($wheres) > 0) {
                $queryString = null;

                foreach ($wheres as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $attribute) {
                            $queryString .= $this->buildQuery($key, $attribute);
                        }
                    } else {
                        $queryString .= $this->buildQuery($key, $value);
                    }
                }

                return substr($queryString, 1, strlen($queryString));
            }
        }

        return '';
    }

    /**
     * Compile the "body" portions of the query.
     *
     * @param  \Kevindierkx\Elicit\Query\Builder $query
     * @return string
     */
    protected function compileBody(Builder $query)
    {
        $body = [];

        $hasBody = !is_null($query->body);

        if ($hasBody) {
            foreach ($query->body as $postField) {
                $body[$postField['column']] = $postField['value'];
            }

            if (count($body) > 0) {
                return $body;
            }
        }

        return '';
    }

    /**
     * Build url encoded portion of the query.
     *
     * @param  string $key
     * @param  string $value
     * @return string
     */
    protected function buildQuery($key, $value)
    {
        return '&' . $key . '=' . urlencode($value);
    }
}
