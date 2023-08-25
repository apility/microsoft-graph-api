<?php

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Facade;

use Microsoft\GraphAPI\ServiceProvider;

$app = null;

// Parse .env
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env') as $line) {
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        $value = preg_replace('/^(\'[^\']*\'|"[^"]*")$/', '$2$3', $value);
        $value = is_int($value) ? (int) $value : $value;
        $value = is_float($value) ? (float) $value : $value;
        $value = in_array($value, ['true', 'false']) ? $value === 'true' : $value;
        $_ENV[$key] = $value;
    }
}

if (!function_exists('env')) {
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return App::getInstance();
        }

        return App::make($abstract, $parameters);
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        return app('request');
    }
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Bootstrap a minimal Laravel-like environment for testing purposes.
 */
$app = new Container;
$app->setInstance($app);

$request = Request::capture();
$cache = new CacheManager($app);
$config = new Repository([
    'microsoft' => require __DIR__ . '/config/microsoft.php',
    'filesystems' => [
        'default' => 'microsoft',
        'cloud' => 'microsoft',
        'disks' => [
            'microsoft' => [
                'driver' => 'microsoft',
                'site_id' => env('MICROSOFT_GRAPH_API_SITE_ID'),
            ],
        ],
    ],
]);

Facade::setFacadeApplication($app);

$app->instance('app', $app);
$app->instance('cache', $cache);
$app->instance('config', $config);
$app->instance('request', $request);

(new FilesystemServiceProvider($app))->register();
(new ServiceProvider($app))->register();
