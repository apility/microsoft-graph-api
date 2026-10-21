<?php

namespace Microsoft\GraphAPI;

use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use League\Flysystem\Filesystem as Flysystem;

use Microsoft\GraphAPI\Auth\Credentials;
use Microsoft\GraphAPI\Client;
use Microsoft\GraphAPI\Filesystem\Adapter as MicrosoftAdapter;

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

        $this->app->singleton(Credentials::class, function () {
            $config = Config::get('microsoft.graph_api');

            return new Credentials(
                $config['tenant_id'],
                $config['app_id'],
                $config['client_secret'],
            );
        });

        $this->app->bind('microsoft.graph_api.client', Client::class);

        Storage::extend('microsoft', function (Container $app, array $config) {
            $adapter = $app->make(MicrosoftAdapter::class, ['config' => $config]);

            return new FilesystemAdapter(
                new Flysystem($adapter, Arr::only($config, ['visibility', 'directory_visibility'])),
                $adapter,
                $config
            );
        });
    }
}
