<?php

namespace Microsoft\GraphAPI;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

use Microsoft\GraphAPI\Auth\Credentials;
use Microsoft\GraphAPI\Client;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/microsoft.php' => config_path('microsoft.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/microsoft.php',
            'microsoft'
        );

        $this->app->singleton('microsoft.graph_api.client', function () {
            $config = Config::get('microsoft.graph_api');

            $credentials = new Credentials(
                $config['tenant_id'],
                $config['app_id'],
                $config['client_secret'],
            );

            return new Client($credentials);
        });
    }
}
