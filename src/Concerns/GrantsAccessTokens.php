<?php

namespace Microsoft\GraphAPI\Concerns;

use Illuminate\Support\Facades\Cache;

use Microsoft\GraphAPI\Auth\AccessToken;
use Microsoft\GraphAPI\Auth\Credentials;
use Microsoft\GraphAPI\Exceptions\AuthenticationException;

trait GrantsAccessTokens
{
    protected Credentials $credentials;

    protected function setCredentials(Credentials $credentials): void
    {
        $this->credentials = $credentials;
    }

    protected function getCredentials(): Credentials
    {
        return $this->credentials;
    }

    /**
     * @return AccessToken
     * @throws AuthenticationException
     */
    protected function accessToken(): AccessToken
    {
        /** @var AccessToken $accessToken */
        $accessToken = Cache::rememberForever('microsoft.graph_api.access_token', function () {
            return AccessToken::request($this->credentials);
        });

        if ($accessToken->isExpired()) {
            Cache::forget('microsoft.graph_api.access_token');
            return $this->accessToken();
        }

        return $accessToken;
    }
}
