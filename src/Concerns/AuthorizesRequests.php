<?php

namespace Microsoft\GraphAPI\Concerns;

use Illuminate\Http\Client\PendingRequest;

trait AuthorizesRequests
{
    use GrantsAccessTokens;

    protected function authorize(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->accessToken());
    }
}
