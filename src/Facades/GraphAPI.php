<?php

namespace Microsoft\GraphAPI\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\Client\PendingRequest request()
 * @method static array get(string $endpoint, ?array $query = null)
 * @method static array post(string $endpoint, ?array $data = null)
 * @method static array put(string $endpoint, ?array $data = null)
 * @method static array patch(string $endpoint, ?array $data = null)
 * @method static array delete(string $endpoint)
 * 
 * @see \Microsoft\GraphAPI\Client
 */
class GraphAPI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'microsoft.graph_api.client';
    }
}
