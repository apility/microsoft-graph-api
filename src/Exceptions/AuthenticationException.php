<?php

namespace Microsoft\GraphAPI\Exceptions;

use Illuminate\Http\Client\Response;

final class AuthenticationException extends Exception
{
    protected function parseError(Response $response): string
    {
        $data = $response->json();
        return sprintf('%s: %s', $data['error'], $data['error_description']);
    }
}
