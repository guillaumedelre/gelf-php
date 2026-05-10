# Monolog integration

[Monolog][monolog] ships with a built-in `GelfHandler` that accepts a `PublisherInterface`. gelf-php provides that publisher.

## Standalone

```php
use Gelf\Publisher;
use Gelf\Transport\UdpTransport;
use Monolog\Logger;
use Monolog\Handler\GelfHandler;

$publisher = new Publisher(new UdpTransport('graylog.internal', 12201));

$log = new Logger('app');
$log->pushHandler(new GelfHandler($publisher, Logger::DEBUG));

$log->info('User logged in', ['user_id' => 42]);
$log->error('Payment failed', ['order_id' => 99]);
```

### Silencing connection errors

Use Monolog's `WhatFailureGroupHandler`[^whatfailure] to swallow exceptions from any handler without touching the transport layer:

```php
use Monolog\Handler\WhatFailureGroupHandler;

$gelfHandler = new GelfHandler($publisher, Logger::DEBUG);
$safeHandler  = new WhatFailureGroupHandler([$gelfHandler]);

$log->pushHandler($safeHandler);
```

Alternatively, wrap the transport itself with `IgnoreErrorTransportWrapper` (see [Transports](transports.md#ignoreerrortransportwrapper)). Both approaches silence errors; `WhatFailureGroupHandler` keeps error handling at the Monolog layer, `IgnoreErrorTransportWrapper` keeps it at the transport layer.

### Adding fields to every message

`Gelf\Logger::$defaultContext` only applies when using `Gelf\Logger` directly. When going through Monolog, use a [processor][monolog-processors] instead:

```php
$log->pushProcessor(function (\Monolog\LogRecord $record): \Monolog\LogRecord {
    return $record->with(extra: array_merge($record->extra, [
        'app'         => 'checkout-service',
        'environment' => 'production',
    ]));
});
```

## Symfony

MonologBundle[^monolog-bundle] has a built-in `gelf` handler type that references a publisher service directly, avoiding the need to define the handler as a service manually.

### Services

```yaml
# config/services.yaml
services:
    gelf.transport:
        class: Gelf\Transport\UdpTransport
        arguments: ['%env(GRAYLOG_HOST)%', '%env(int:GRAYLOG_PORT)%']

    gelf.publisher:
        class: Gelf\Publisher
        arguments: ['@gelf.transport']
```

### Monolog config

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        graylog:
            type: gelf
            publisher:
                id: gelf.publisher
            level: warning
```

### Silencing connection errors

Use `whatfailuregroup` to wrap the gelf handler instead of adding `IgnoreErrorTransportWrapper` to the transport:

```yaml
monolog:
    handlers:
        graylog_safe:
            type: whatfailuregroup
            members: [graylog]
        graylog:
            type: gelf
            publisher:
                id: gelf.publisher
            level: warning
```

### Adding fields to every message

Use a Monolog [processor][monolog-processors] tagged as `monolog.processor`:

```php
// src/Logging/AppContextProcessor.php
namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class AppContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: array_merge($record->extra, [
            'app'         => 'checkout-service',
            'environment' => $_ENV['APP_ENV'] ?? 'prod',
        ]));
    }
}
```

```yaml
# config/services.yaml
App\Logging\AppContextProcessor:
    tags: [{ name: monolog.processor }]
```

### Swap transport without touching the rest

The services config is intentionally thin so that swapping the transport only requires changing `gelf.transport`:

```yaml
# TCP
gelf.transport:
    class: Gelf\Transport\TcpTransport
    arguments: ['%env(GRAYLOG_HOST)%', '%env(int:GRAYLOG_PORT)%']

# HTTP
gelf.transport:
    class: Gelf\Transport\HttpTransport
    factory: ['Gelf\Transport\HttpTransport', 'fromUrl']
    arguments: ['%env(GRAYLOG_URL)%']
```

### Environment variables

```dotenv
# .env
GRAYLOG_HOST=graylog.internal
GRAYLOG_PORT=12201
```

## Laravel

```php
// config/logging.php
'channels' => [
    'graylog' => [
        'driver'  => 'monolog',
        'handler' => Monolog\Handler\GelfHandler::class,
        'with'    => [
            'publisher' => new \Gelf\Publisher(
                new \Gelf\Transport\UdpTransport(
                    env('GRAYLOG_HOST', '127.0.0.1'),
                    (int) env('GRAYLOG_PORT', 12201)
                )
            ),
        ],
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

```dotenv
LOG_CHANNEL=graylog
GRAYLOG_HOST=graylog.internal
GRAYLOG_PORT=12201
```

[monolog]: https://github.com/Seldaek/monolog
[monolog-processors]: https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#processors
[^whatfailure]: `WhatFailureGroupHandler` passes each record to all member handlers and swallows any exception they throw.
[^monolog-bundle]: [symfony/monolog-bundle](https://github.com/symfony/monolog-bundle) provides Symfony integration for Monolog, including the `type: gelf` handler shorthand.
