<?php

namespace Microsoft\GraphAPI\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\Client\PendingRequest request()
 * @method static \Illuminate\Http\Client\Response get(string $endpoint, ?array $query = null)
 * @method static \Illuminate\Http\Client\Response post(string $endpoint, ?array $data = null)
 * @method static \Illuminate\Http\Client\Response put(string $endpoint, ?array $data = null)
 * @method static \Illuminate\Http\Client\Response patch(string $endpoint, ?array $data = null)
 * @method static \Illuminate\Http\Client\Response delete(string $endpoint)
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
