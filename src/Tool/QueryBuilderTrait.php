<?php

namespace KevinDierkx\Elicit\Tool;

trait QueryBuilderTrait
{
    /**
     * Build a query string from an array.
     *
     * @param array $params
     *
     * @return string
     */
    protected function buildQueryString(array $params)
    {
        return http_build_query($params, null, '&');
    }
}
