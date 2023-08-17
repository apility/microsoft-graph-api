# Microsoft Graph API client library

This package provides a PHP client library for working with the Microsoft Graph API.

## Table of Contents

- [Installation](#installation)
- [Laravel](#laravel)
    - [Configuration](#configuration)
    - [Usage](#usage)
- [Without Laravel (Plain PHP)](#without-laravel-plain-php)
    - [Usage](#usage-1)
- [License](#license)

## Installation

```bash
composer require apility/microsoft-graph-api
```

## Laravel

### Configuration

Export the configuration file:

```bash
php artisan vendor:publish --provider="Microsoft\GraphAPI\ServiceProvider" --tag="config"
```

Add the following environment variables to your `.env` file:

```dotenv
MICROSOFT_GRAPH_API_TENANT_ID=<your-tenant-id>
MICROSOFT_GRAPH_API_APP_ID=<your-app-id>
MICROSOFT_GRAPH_API_CLIENT_SECRET=<your-client-secret>
```

### Usage

```php
use Microsoft\GraphAPI\Facades\GraphAPI;

$me = GraphAPI::get('/me');
```

## Without Laravel (Plain PHP)

### Usage

```php
use Microsoft\GraphAPI\GraphAPI\Client;
use Microsoft\GraphAPI\GraphAPI\Auth\Credentials;

$credentials = new Credentials(
    '<your-tenant-id>',
    '<your-app-id>',
    '<your-client-secret>'
);

$client = new Client($credentials);

$me = $client->get('/me');
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

Copyright Apility AS © 2023