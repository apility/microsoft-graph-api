<?php

namespace Microsoft\GraphAPI\Exceptions;

use Illuminate\Http\Client\Response;

final class GraphAPIException extends Exception
{
    protected function parseError(Response $response): string
    {
        $data = $response->json()['error'];
        return sprintf('%s: %s', $data['code'], $data['message']);
    }
}
