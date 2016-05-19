<?php

namespace Kevindierkx\Elicit\Tool;

trait BasicAuthorizationTrait
{
    /**
     * Returns the identifier parameter for the 'basic' authorization.
     *
     * @return string
     */
    abstract public function getIdentifier();

    /**
     * Returns the secret parameter for the 'basic' authorization.
     *
     * @return string
     */
    abstract public function getSecret();

    /**
     * Returns the access token for the 'basic' authorization.
     *
     * @return string
     */
    public function getAccessToken()
    {
        return base64_encode($this->getIdentifier() . ':' . $this->getSecret());
    }

    /**
     * Returns the authorization headers for the 'basic' authorization.
     *
     * @param  string|null  $token
     * @return array
     */
    protected function getAuthorizationHeaders($token = null)
    {
        return ['Authorization' => 'Basic ' . $token];
    }
}
