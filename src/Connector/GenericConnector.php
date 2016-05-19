<?php

namespace Kevindierkx\Elicit\Connector;

use InvalidArgumentException;
use Kevindierkx\Elicit\Connector\Exception\ProviderException;
use Psr\Http\Message\ResponseInterface;

class GenericConnector extends AbstractConnector
{
    /**
     * @var string
     */
    private $urlApi;

    /**
     * @var string
     */
    private $responseError = 'error';
    /**
     * @var string
     */
    private $responseCode;

    /**
     * Create a new instance of the GenericConnector.
     *
     * @param  array  $options
     * @param  array  $collaborators
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->assertRequiredOptions($options);

        $possible   = $this->getConfigurableOptions();
        $configured = array_intersect_key($options, array_flip($possible));

        foreach ($configured as $key => $value) {
            $this->$key = $value;
        }

        // Remove options already used locally.
        $options = array_diff_key($options, $configured);

        parent::__construct($options, $collaborators);
    }

    /**
     * Returns all options that can be configured.
     *
     * @return array
     */
    protected function getConfigurableOptions()
    {
        return array_merge($this->getRequiredOptions(), [
            'responseError',
            'responseCode',
        ]);
    }

    /**
     * Returns all options that are required.
     *
     * @return array
     */
    protected function getRequiredOptions()
    {
        return [
            'urlApi',
        ];
    }

    /**
     * @inheritdoc
     */
    public function getBaseApiUrl()
    {
        return $this->urlApi;
    }

    /**
     * Verifies that all required options have been passed.
     *
     * @param  array  $options
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertRequiredOptions(array $options)
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (! empty($missing)) {
            throw new InvalidArgumentException(
                'Required options not defined: ' . implode(', ', array_keys($missing))
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (! empty($data[$this->responseError])) {
            $error = $data[$this->responseError];
            $code  = $this->responseCode ? $data[$this->responseCode] : 0;

            throw new ProviderException($error, $code, $data);
        }
    }
}
