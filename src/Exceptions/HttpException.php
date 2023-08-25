<?php

namespace Microsoft\GraphAPI\Exceptions;

use Illuminate\Http\Client\Response;

abstract class HttpException extends Exception
{
    protected Response $response;

    public function __construct(Response $response = null)
    {
        $this->response = $response;
        parent::__construct($this->formattedMessage(), $response->status());
    }

    protected function formattedMessage()
    {
        return 'Microsoft Graph API: ' . $this->parseError($this->response);
    }

    protected function parseError(Response $response): string
    {
        return 'HTTP ' . $response->status() . ' ' . $response->reasonPhrase();
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
