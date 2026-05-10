# Transports

A transport serializes a `Message` and sends it to a GELF-compatible backend. All transports implement `TransportInterface` and can be wrapped with the decorators described at the end of this page.

## UDP

The default transport. Lightweight, connectionless, no delivery guarantee.

```php
use Gelf\Transport\UdpTransport;

// defaults: 127.0.0.1:12201, LAN chunk size
$transport = new UdpTransport();

// explicit host, port and chunk size
$transport = new UdpTransport('graylog.internal', 12201, UdpTransport::CHUNK_SIZE_LAN);
```

| Constant | Value | Use case |
|---|---|---|
| `CHUNK_SIZE_LAN` | 8154 bytes | Local network |
| `CHUNK_SIZE_WAN` | 1420 bytes | Internet / VPN |

Set `chunkSize` to `0` to disable chunking entirely (only safe if your messages are always small):

```php
$transport = new UdpTransport('graylog.internal', 12201, 0);
```

UDP uses `CompressedJsonEncoder` by default (gzip + JSON). The GELF chunking protocol supports up to 128 chunks per message.

## TCP

Reliable, ordered delivery. Messages are delimited by a null byte.

```php
use Gelf\Transport\TcpTransport;

// defaults: 127.0.0.1:12201
$transport = new TcpTransport();

// explicit host and port
$transport = new TcpTransport('graylog.internal', 12201);
```

### TCP with SSL

Port `12202` enables SSL automatically. Use `SslOptions` for custom CA or cipher configuration:

```php
use Gelf\Transport\TcpTransport;
use Gelf\Transport\SslOptions;

$ssl = new SslOptions();
$ssl->setVerifyPeer(true);
$ssl->setVerifyPeerName(true);
$ssl->setCaFile('/etc/ssl/certs/internal-ca.pem');
// $ssl->setAllowSelfSigned(true); // for development only

$transport = new TcpTransport('graylog.internal', 12202, $ssl);
```

TCP uses `JsonEncoder` (plain JSON, null-byte safe).

## HTTP

Sends messages via HTTP `POST` to the GELF HTTP input.

```php
use Gelf\Transport\HttpTransport;

// defaults: 127.0.0.1:12202, path /gelf
$transport = new HttpTransport();

// explicit parameters
$transport = new HttpTransport('graylog.internal', 12202, '/gelf');

// from a URL (convenient for config-file driven setups)
$transport = HttpTransport::fromUrl('http://graylog.internal:12202/gelf');
$transport = HttpTransport::fromUrl('https://graylog.internal/gelf');    // SSL on port 443
```

### HTTP with authentication

```php
$transport = new HttpTransport('graylog.internal', 12202, '/gelf');
$transport->setAuthentication('user', 'secret');
```

### HTTP through a proxy

```php
$transport->setProxyUri('http://proxy.internal:3128');
$transport->setRequestTimeout(5.0); // seconds, float
```

Port `443` enables SSL automatically. You can also pass an `SslOptions` instance as the fourth constructor argument for custom SSL configuration.

## AMQP

Publishes messages to an AMQP[^amqp] exchange. Requires the [`amqp` PHP extension][amqp-ext].

> **Note:** `AmqpTransport` is excluded from the project's Psalm static analysis configuration and receives less type coverage than the other transports.

```php
use Gelf\Transport\AmqpTransport;

$connection = new \AMQPConnection([
    'host'     => 'rabbitmq.internal',
    'login'    => 'gelf',
    'password' => 'secret',
]);
$connection->connect();

$channel  = new \AMQPChannel($connection);
$exchange = new \AMQPExchange($channel);
$exchange->setName('gelf');
$exchange->setType(AMQP_EX_TYPE_FANOUT);
$exchange->declareExchange();

$queue = new \AMQPQueue($channel);
$queue->setName('gelf');
$queue->setFlags(AMQP_DURABLE);
$queue->declareQueue();
$queue->bind($exchange->getName());

$transport = new AmqpTransport($exchange, $queue);
```

## Transport wrappers

Wrappers implement `TransportInterface` and decorate an existing transport. They can be stacked.

### IgnoreErrorTransportWrapper

Swallows all exceptions from `send()`. Useful when logging must never crash the application:

```php
use Gelf\Transport\UdpTransport;
use Gelf\Transport\IgnoreErrorTransportWrapper;

$transport = new IgnoreErrorTransportWrapper(new UdpTransport());

// Inspect what went wrong after the fact
$last = $transport->getLastError(); // ?Throwable
```

### RetryTransportWrapper

Retries `send()` up to `$maxRetries` times on any exception. Pass a callable to retry only on specific exception types:

```php
use Gelf\Transport\TcpTransport;
use Gelf\Transport\RetryTransportWrapper;

$transport = new RetryTransportWrapper(
    new TcpTransport('graylog.internal', 12201),
    maxRetries: 3
);

// Retry only on connection errors
$transport = new RetryTransportWrapper(
    new TcpTransport('graylog.internal', 12201),
    maxRetries: 3,
    exceptionMatcher: fn (\Throwable $e) => $e instanceof \RuntimeException
);
```

Set `maxRetries` to `0` for unlimited retries.

### KeepAliveRetryTransportWrapper

A `RetryTransportWrapper` preset that retries once specifically on keep-alive connection drops. Useful with HTTP and TCP transports behind load balancers:

```php
use Gelf\Transport\HttpTransport;
use Gelf\Transport\KeepAliveRetryTransportWrapper;

$transport = new KeepAliveRetryTransportWrapper(
    new HttpTransport('graylog.internal', 12202, '/gelf')
);
```

### Stacking wrappers

```php
use Gelf\Transport\TcpTransport;
use Gelf\Transport\RetryTransportWrapper;
use Gelf\Transport\IgnoreErrorTransportWrapper;

// Retry up to 3 times, then silently discard
$transport = new IgnoreErrorTransportWrapper(
    new RetryTransportWrapper(new TcpTransport('graylog.internal', 12201), 3)
);
```

[amqp-ext]: https://www.php.net/manual/en/book.amqp.php
[^amqp]: Advanced Message Queuing Protocol: an open standard for message broker communication.
