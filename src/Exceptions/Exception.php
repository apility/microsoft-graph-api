<?php

namespace Microsoft\GraphAPI\Exceptions;

use Exception as BaseException;

use Illuminate\Http\Client\Response;

class Exception extends BaseException
{
    protected Response $response;

    public function __construct(Response $response)
    {
        $message = $this->parseError($response);
        parent::__construct($message, $response->status());
        $this->response = $response;
    }

    protected function parseError(Response $response): string
    {
        return 'Microsoft Graph API Error';
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
