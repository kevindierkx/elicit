<?php

namespace Kevindierkx\Elicit;

use GuzzleHttp\Psr7\Request;
use Kevindierkx\Elicit\Tool\QueryBuilderTrait;
use Psr\Http\Message\StreamInterface;

class RequestFactory
{
    use QueryBuilderTrait;

    /**
     * Creates a PSR-7 Request instance.
     *
     * @param  null|string                                 $method   HTTP method for the request.
     * @param  null|string                                 $uri      URI for the request.
     * @param  array                                       $headers  Headers for the message.
     * @param  string|array|resource|StreamInterface|null  $body     Message body.
     * @param  string                                      $version  HTTP protocol version.
     * @return Request
     */
    public function getRequest($method, $uri, array $headers = [], $body = null, $version = '1.1')
    {
        if (! is_null($body) && gettype($body) === 'array') {
            $body = $this->buildQueryString($body);
        }

        if (! is_null($body) &&
            ! $body instanceof StreamInterface &&
            ! gettype($body) === 'resource' &&
            ! gettype($body) === 'string'
        ) {
            throw new InvalidArgumentException(sprintf("\$body must be one of type string|array|resource|StreamInterface|null, %s given.", gettype($body)));
        }

        return new Request($method, $uri, $headers, $body, $version);
    }

    /**
     * Parses simplified options.
     *
     * @param  array  $options
     * @return array
     */
    protected function parseOptions(array $options)
    {
        $defaults = [
            'headers' => [],
            'body'    => null,
            'version' => '1.1',
        ];

        return array_merge($defaults, $options);
    }

    /**
     * Creates a request using a simplified array of options.
     *
     * @param  null|string  $method
     * @param  null|string  $uri
     * @param  array        $options
     * @return Request
     */
    public function getRequestWithOptions($method, $uri, array $options = [])
    {
        $options = $this->parseOptions($options);

        return $this->getRequest(
            $method,
            $uri,
            $options['headers'],
            $options['body'],
            $options['version']
        );
    }
}
