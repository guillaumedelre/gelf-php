# Architecture

gelf-php is organized around three layers: **Message**, **Publisher**, and **Transport**.

```
Your code
   │
   ▼
Gelf\Logger          (PSR-3 facade, optional)
   │
   ▼
Gelf\Publisher       (validates, fans out to one or more transports)
   │
   ├──► UdpTransport
   ├──► TcpTransport
   ├──► HttpTransport  ──► Graylog / GELF-compatible backend
   └──► AmqpTransport ──► RabbitMQ / AMQP broker
```

## Message

`Gelf\Message` holds the data for a single log event. It maps to the [GELF 1.1 specification][gelf-spec].

```php
use Gelf\Message;
use Psr\Log\LogLevel;

$message = new Message();
$message
    ->setShortMessage('Payment failed')          // required, one line
    ->setFullMessage('Card declined: NSF ...')   // optional, multi-line detail
    ->setLevel(LogLevel::ERROR)                  // PSR-3 level or syslog int
    ->setAdditional('order_id', 42)              // any extra field
    ->setAdditional('amount', 99.90)
;
```

`setLevel()` accepts both PSR-3 strings (`'error'`) and syslog integers (`3`). All additional fields are prefixed with `_` automatically when serialized.

`setTimestamp()` accepts a `float`, `int`, or `DateTimeInterface`:

```php
$message->setTimestamp(new \DateTimeImmutable('2024-01-15 10:30:00'));
```

## Publisher

`Gelf\Publisher` validates messages and dispatches them to one or more transports. It implements `PublisherInterface`, so you can substitute it with a custom implementation in tests.

```php
use Gelf\Publisher;
use Gelf\Transport\UdpTransport;
use Gelf\Transport\TcpTransport;

$publisher = new Publisher();
$publisher->addTransport(new UdpTransport('graylog.internal', 12201));
$publisher->addTransport(new TcpTransport('graylog-backup.internal', 12201));

// Sends to both transports
$publisher->publish($message);
```

`Publisher` throws a `RuntimeException` if no transport is registered or if the message fails validation (empty `shortMessage`, for example).

## Logger

`Gelf\Logger` is a [PSR-3][psr3] `LoggerInterface` built on top of `Publisher`. It handles placeholder interpolation, context serialization, and exception unpacking.

```php
use Gelf\Logger;
use Gelf\Publisher;
use Gelf\Transport\UdpTransport;

// Zero-config: logs to udp://localhost:12201
$logger = new Logger();

// Or wire your own publisher
$publisher = new Publisher(new UdpTransport('graylog.internal', 12201));
$logger = new Logger($publisher);

$logger->info('User {user} logged in', ['user' => 'alice']);
$logger->error('Payment failed', ['order_id' => 42, 'amount' => 99.90]);
```

### Default context

Fields that should appear on every message can be set once:

```php
$logger = new Logger($publisher, [
    'app'         => 'checkout-service',
    'environment' => 'production',
]);

// Both messages will carry app and environment fields
$logger->info('Order placed', ['order_id' => 1]);
$logger->error('Payment failed', ['order_id' => 2]);
```

> **Note:** `defaultContext` only applies when using `Gelf\Logger` directly. When routing through Monolog, use a processor instead. See [Monolog integration](monolog.md#adding-fields-to-every-message).

### Exception logging

Pass an `Exception` under the `'exception'` key. The logger unpacks the stack trace chain into `fullMessage` and adds `file` and `line` as additional fields:

```php
try {
    $paymentGateway->charge($order);
} catch (\Exception $e) {
    $logger->error('Payment gateway error', ['exception' => $e, 'order_id' => $order->id]);
}
```

## Custom MessageValidator

`Publisher` uses `Gelf\MessageValidator` by default. You can inject a custom validator implementing `MessageValidatorInterface`:

```php
use Gelf\Publisher;
use Gelf\MessageValidatorInterface;
use Gelf\MessageInterface;

class LaxValidator implements MessageValidatorInterface
{
    public function validate(MessageInterface $message, string &$reason = ''): bool
    {
        return true; // skip all validation
    }
}

$publisher = new Publisher(validator: new LaxValidator());
```

[gelf-spec]: https://go2docs.graylog.org/current/getting_in_log_data/gelf.html
[psr3]: https://www.php-fig.org/psr/psr-3/
