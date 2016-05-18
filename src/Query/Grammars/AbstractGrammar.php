<?php

namespace Kevindierkx\Elicit\Query\Grammars;

abstract class AbstractGrammar
{
    /**
     * The parameters that are used for the path.
     *
     * @var array
     */
    protected $replacedParameters = array();

    /**
     * Check for named parameters in the path.
     *
     * @param  string  $path
     * @return boolean
     */
    protected function hasNamedParameters($path)
    {
        return (preg_match('/\{(.*?)\??\}/', $path) !== 0);
    }

    /**
     * Replace all of the named parameters in the path.
     *
     * @param  string  $path
     * @param  array   $query
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function replaceNamedParameters($path, array $query = array())
    {
        $grammar = $this;

        // Here we replace all named parameters in the path with values
        // from the query. Every value replaced will be bound to the
        // $replacedWheres variable of this class.
        return preg_replace_callback('/\{(.*?)\??\}/', function ($m) use ($grammar, $query) {
            $namedParameter = $m[1];

            $parameter = array_first($query, function ($key, $value) use ($namedParameter) {
                return $value['column'] == $namedParameter;
            });

            $hasParameter = ! is_null($parameter);

            // When the named parameter is found we add it to the replaced wheres
            // and replace it in the path.
            if ($hasParameter) {
                $grammar->addReplacedParameter($parameter);

                return $parameter['value'];
            }

            // We stop here when a named parameter is not provided.
            // Named parameters are most likely required and continuing
            // would result in unexpected results.
            throw new \InvalidArgumentException("Named parameter [$namedParameter] missing from request");
        }, $path);
    }

    /**
     * Add parameter to the replaced parameters variable.
     *
     * @param  array  $parameter
     */
    public function addReplacedParameter(array $parameter)
    {
        $this->replacedParameters[] = $parameter;
    }

    /**
     * Validate the existence of a replaced parameter.
     *
     * @param  array   $parameter
     * @return boolean
     */
    public function hasReplacedParameter(array $parameter)
    {
        foreach ($this->replacedParameters as $replacedParameter) {
            if ($replacedParameter['column'] === $parameter['column'] &&
                $replacedParameter['value']  === $parameter['value']
            ) {
                return true;
            }
        }

        return false;
    }
}
