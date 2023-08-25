<?php

namespace Microsoft\GraphAPI\Auth;

use JsonSerializable;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Support\Jsonable;

use Microsoft\GraphAPI\Exceptions\AuthenticationException;

final class AccessToken implements JsonSerializable, Jsonable
{
    const SCOPE_DEFAULT = 'https://graph.microsoft.com/.default';

    protected string $access_token;

    protected Carbon $expires_at;
    protected Carbon $granted_at;

    protected function __construct(string $access_token, int $expires_in)
    {
        $this->access_token = $access_token;
        $this->expires_at = Carbon::now()->addSeconds($expires_in);
        $this->granted_at = Carbon::now();
    }

    public function getAccessToken(): string
    {
        return $this->access_token;
    }

    public function getExpiresIn(): int
    {
        return $this->expires_at->diffInSeconds(Carbon::now());
    }

    public function getGrantedAt(): Carbon
    {
        return $this->granted_at;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function request(Credentials $credentials, string $scope = AccessToken::SCOPE_DEFAULT): AccessToken
    {
        $response = Http::asForm()
            ->post(
                sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $credentials->gettenantId()),
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $credentials->getAppId(),
                    'client_secret' => $credentials->getClientSecret(),
                    'scope' => $scope
                ]
            );

        try {
            $data = $response->throw()
                ->json();

            return new AccessToken($data['access_token'], $data['expires_in']);
        } catch (HttpClientException $e) {
            throw new AuthenticationException($response);
        }
    }

    public function __toString(): string
    {
        return $this->getAccessToken();
    }

    public function __debugInfo(): array
    {
        return [
            'access_token' => $this->getAccessToken(),
            'expires_at' => $this->expires_at->toDateTimeString(),
            'granted_at' => $this->granted_at->toDateTimeString(),
            'is_expired' => $this->isExpired()
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
