<?php

namespace Microsoft\GraphAPI\Auth;

final class Credentials
{
    protected string $tenantId;
    protected string $appId;
    protected string $clientSecret;

    public function __construct(string $tenantId, string $appId, string $clientSecret)
    {
        $this->tenantId = $tenantId;
        $this->appId = $appId;
        $this->clientSecret = $clientSecret;
    }

    public function gettenantId(): string
    {
        return $this->tenantId;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }
}
