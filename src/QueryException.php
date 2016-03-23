<?php namespace Kevindierkx\Elicit;

use RuntimeException;

class QueryException extends RuntimeException
{
    /**
     * The query for the request.
     *
     * @var string
     */
    protected $query;

    /**
     * Create a new query exception instance.
     *
     * QueryException constructor.
     * @param string $query
     * @param int $previous
     */
    public function __construct($query, $previous)
    {
        $this->query = $query;

        $this->code  = $previous->getCode();

        $this->message = $this->formatMessage($query, $previous);
    }

    /**
     * Format the request error message.
     *
     * @param  string      $query
     * @param  \Exception  $previous
     * @return string
     */
    protected function formatMessage($query, $previous)
    {
        $response = $previous->getResponse();

        return 'Request returned with [' . $response->getReasonPhrase() . '] (' . $response->getEffectiveUrl() . ')';
    }

    /**
     * Get the query for the response.
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }
}
