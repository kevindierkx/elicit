<?php

namespace Kevindierkx\Elicit\Connector;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use InvalidArgumentException;
use Kevindierkx\Elicit\RequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

abstract class AbstractConnector
{
    /**
     * @var string
     */
    const METHOD_GET = 'GET';

    /**
     * @var string
     */
    const METHOD_POST = 'POST';

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * Constructs an Elicit service provider.
     *
     * @param  array  $options  An array of options to set on this provider.
     *     Individual providers may introduce more options, as needed.
     * @param  array  $collaborators  An array of collaborators
     *     that may be used to override this provider's default behavior.
     *     Collaborators include `requestFactory` and `$httpClient`.
     *     Individual providers may introduce more collaborators, as needed.
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        foreach ($options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->{$option} = $value;
            }
        }

        if (empty($collaborators['requestFactory'])) {
            $collaborators['requestFactory'] = new RequestFactory();
        }
        $this->setRequestFactory($collaborators['requestFactory']);

        if (empty($collaborators['httpClient'])) {
            $client_options = $this->getAllowedClientOptions($options);

            $collaborators['httpClient'] = new HttpClient(
                array_intersect_key($options, array_flip($client_options))
            );
        }
        $this->setHttpClient($collaborators['httpClient']);
    }

    /**
     * Return the list of options that can be passed to the HttpClient
     *
     * @param  array  $options
     * @return array
     */
    protected function getAllowedClientOptions(array $options)
    {
        $client_options = ['timeout', 'proxy'];

        // Only allow turning off ssl verification for proxies.
        if (! empty($options['proxy'])) {
            $client_options[] = 'verify';
        }

        return $client_options;
    }

    /**
     * Sets the request factory instance.
     *
     * @param  RequestFactory  $factory
     * @return self
     */
    public function setRequestFactory(RequestFactory $factory)
    {
        $this->requestFactory = $factory;

        return $this;
    }

    /**
     * Returns the request factory instance.
     *
     * @return RequestFactory
     */
    public function getRequestFactory()
    {
        return $this->requestFactory;
    }

    /**
     * Sets the HTTP client instance.
     *
     * @param  HttpClientInterface  $client
     * @return self
     */
    public function setHttpClient(HttpClientInterface $client)
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Returns the HTTP client instance.
     *
     * @return HttpClientInterface
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Returns the base domain.
     *
     * @return string
     */
    abstract public function getBaseDomain();

    /**
     * Returns the access token.
     *
     * @return string
     */
    abstract public function getAccessToken();

    /**
     * Returns a PSR-7 request instance that is not authenticated.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  array   $options
     * @return RequestInterface
     */
    public function getRequest($method, $url, array $options = [])
    {
        return $this->createRequest($method, $url, null, $options);
    }

    /**
     * Returns an authenticated PSR-7 request instance.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  string  $token
     * @param  array   $options  Any of "headers", "body", and "protocolVersion".
     * @return RequestInterface
     */
    public function getAuthenticatedRequest($method, $url, $token, array $options = [])
    {
        return $this->createRequest($method, $url, $token, $options);
    }

    /**
     * Creates a PSR-7 request instance.
     *
     * @param  string       $method
     * @param  string       $url
     * @param  string|null  $token
     * @param  array        $options
     * @return RequestInterface
     */
    protected function createRequest($method, $url, $token, array $options)
    {
        $defaults = [
            'headers' => $this->getHeaders($token),
        ];

        $options = array_merge_recursive($defaults, $options);
        $factory = $this->getRequestFactory();

        return $factory->getRequestWithOptions($method, $url, $options);
    }

    /**
     * Returns the default headers used by this provider.
     * Typically this is used to set 'Accept' or 'Content-Type' headers.
     *
     * @return array
     */
    protected function getDefaultHeaders()
    {
        return [];
    }

    /**
     * Returns the authorization headers used by this provider.
     *
     * @param  string|null  $token
     * @return array
     */
    protected function getAuthorizationHeaders($token = null)
    {
        return [];
    }

    /**
     * Returns all headers used by this provider for a request.
     * The request will be authenticated if an access token is provided.
     *
     * @param  string|null  $token
     * @return array
     */
    public function getHeaders($token = null)
    {
        if ($token) {
            return array_merge(
                $this->getDefaultHeaders(),
                $this->getAuthorizationHeaders($token)
            );
        }

        return $this->getDefaultHeaders();
    }

    /**
     * Sends a request instance and returns a response instance.
     *
     * @param  RequestInterface  $request
     * @return ResponseInterface
     */
    protected function sendRequest(RequestInterface $request)
    {
        try {
            $response = $this->getHttpClient()->send($request);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $response;
    }

    /**
     * Sends a request and returns the parsed response.
     *
     * @param  RequestInterface $request
     * @return mixed
     */
    public function getResponse(RequestInterface $request)
    {
        $response = $this->sendRequest($request);
        $parsed   = $this->parseResponse($response);

        $this->checkResponse($response, $parsed);

        return $parsed;
    }

    /**
     * Attempts to parse a JSON response.
     *
     * @param  string  $content  JSON content from response body
     * @return array             Parsed JSON data
     * @throws UnexpectedValueException
     */
    protected function parseJson($content)
    {
        $content = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedValueException(sprintf(
                "Failed to parse JSON response: %s",
                json_last_error_msg()
            ));
        }

        return $content;
    }

    /**
     * Returns the content type header of a response.
     *
     * @param  ResponseInterface  $response
     * @return string
     */
    protected function getContentType(ResponseInterface $response)
    {
        return join(';', (array) $response->getHeader('content-type'));
    }

    /**
     * Parses the response according to its content-type header.
     *
     * @param  ResponseInterface  $response
     * @return array
     * @throws UnexpectedValueException
     */
    protected function parseResponse(ResponseInterface $response)
    {
        $content = (string) $response->getBody();
        $type    = $this->getContentType($response);

        if (strpos($type, 'urlencoded') !== false) {
            parse_str($content, $parsed);
            return $parsed;
        }

        // Attempt to parse the string as JSON regardless of content type,
        // since some providers use non-standard content types. Only throw an
        // exception if the JSON could not be parsed when it was expected to.
        try {
            return $this->parseJson($content);
        } catch (UnexpectedValueException $e) {
            if (strpos($type, 'json') !== false) {
                throw $e;
            }

            return $content;
        }
    }

    /**
     * Checks a provider response for errors.
     *
     * @param  ResponseInterface  $response
     * @param  array|string       $data
     * @return void
     * @throws ProviderException
     */
    abstract protected function checkResponse(ResponseInterface $response, $data);
}
