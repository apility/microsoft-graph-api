<?php

namespace Microsoft\GraphAPI\Exceptions;

final class CredentialException extends Exception
{
    public function __construct(string $message = 'Invalid credentials')
    {
        parent::__construct($message);
    }
}
