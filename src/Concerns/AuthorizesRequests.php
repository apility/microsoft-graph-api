<?php

namespace Microsoft\GraphAPI\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Microsoft\GraphAPI\Auth\AccessToken;

trait AuthorizesRequests
{
    use GrantsAccessTokens;

    protected function authorize(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->accessToken());
    }
}
