<?php

namespace Microsoft\GraphAPI;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Cache;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use League\Flysystem\Filesystem as Flysystem;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory as MemoryStore;
use League\Flysystem\FilesystemInterface;

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
            return $this->adapt($this->createFlysystem(
                $app->make(MicrosoftAdapter::class, ['config' => $config]),
                $config
            ));
        });
    }

    /**
     * Adapt the filesystem implementation.
     *
     * @param  \League\Flysystem\FilesystemInterface  $filesystem
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function adapt(FilesystemInterface $filesystem)
    {
        return new FilesystemAdapter($filesystem);
    }

    /**
     * Create a Flysystem instance with the given adapter.
     *
     * @param  \League\Flysystem\AdapterInterface  $adapter
     * @param  array  $config
     * @return \League\Flysystem\FilesystemInterface
     */
    protected function createFlysystem(AdapterInterface $adapter, array $config)
    {
        $cache = Arr::pull($config, 'cache');

        $config = Arr::only($config, ['visibility', 'disable_asserts', 'url', 'temporary_url']);

        if ($cache) {
            $adapter = new CachedAdapter($adapter, $this->createCacheStore($cache));
        }

        return new Flysystem($adapter, count($config) > 0 ? $config : null);
    }

    /**
     * Create a cache store instance.
     *
     * @param  mixed  $config
     * @return \League\Flysystem\Cached\CacheInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function createCacheStore($config)
    {
        if ($config === true) {
            return new MemoryStore;
        }

        return new Cache(
            $this->app['cache']->store($config['store']),
            $config['prefix'] ?? 'flysystem',
            $config['expire'] ?? null
        );
    }
}
