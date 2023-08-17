<?php

namespace Microsoft\GraphAPI;

use Closure;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

use Microsoft\GraphAPI\Auth\Credentials;
use Microsoft\GraphAPI\Concerns\AuthorizesRequests;
use Microsoft\GraphAPI\Exceptions\GraphAPIException;

/**
 * @method array get(string $endpoint, ?array $query = null)
 * @method array post(string $endpoint, ?array $data = null)
 * @method array put(string $endpoint, ?array $data = null)
 * @method array patch(string $endpoint, ?array $data = null)
 * @method array delete(string $endpoint)
 */
class Client
{
    use AuthorizesRequests;

    public function __construct(Credentials $credentials)
    {
        $this->setCredentials($credentials);
    }

    protected function request(): PendingRequest
    {
        $request = Http::baseUrl('https://graph.microsoft.com/v1.0/');

        return $this->authorize($request);
    }

    /**
     * @param Response $response
     * @return Response
     * @throws GraphAPIException
     */
    protected function response(Response $response): Response
    {
        try {
            return $response
                ->throw();
        } catch (HttpClientException $e) {
            throw new GraphAPIException($response);
        }
    }

    protected function handle(Closure $callback): array
    {
        return $this->response($callback($this->request()))
            ->json();
    }

    public function __call(string $name, array $arguments = []): array
    {
        return $this->handle(
            fn (PendingRequest $request) => $request->$name(...$arguments)
        );
    }
}
