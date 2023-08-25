<?php

namespace Microsoft\GraphAPI\Auth;

use Microsoft\GraphAPI\Exceptions\CredentialException;

final class Credentials
{
    protected ?string $tenantId;
    protected ?string $appId;
    protected ?string $clientSecret;

    public function __construct(?string $tenantId, ?string $appId, ?string $clientSecret)
    {
        $this->tenantId = $tenantId;
        $this->appId = $appId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @return string
     * @throws CredentialException
     */
    public function getTenantId(): string
    {
        if ($this->tenantId === null) {
            throw new CredentialException('Tenant ID is not set.');
        }

        return $this->tenantId;
    }

    /**
     * @return string
     * @throws CredentialException
     */
    public function getAppId(): string
    {
        if ($this->tenantId === null) {
            throw new CredentialException('App ID is not set.');
        }

        return $this->appId;
    }

    /**
     * @return string
     * @throws CredentialException
     */
    public function getClientSecret(): string
    {
        if ($this->tenantId === null) {
            throw new CredentialException('Client Secret is not set.');
        }

        return $this->clientSecret;
    }
}
