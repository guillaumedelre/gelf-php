gelf-php [![Latest Stable Version](https://img.shields.io/packagist/v/graylog2/gelf-php.svg?style=flat-square)](https://packagist.org/packages/graylog2/gelf-php) [![Total Downloads](https://img.shields.io/packagist/dt/graylog2/gelf-php.svg?style=flat-square)](https://packagist.org/packages/graylog2/gelf-php) 
========
[![Build Status](https://img.shields.io/travis/com/bzikarsky/gelf-php.svg?style=flat-square)](https://travis-ci.com/bzikarsky/gelf-php)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/bzikarsky/gelf-php.svg?style=flat-square)](https://scrutinizer-ci.com/g/bzikarsky/gelf-php/)
[![Scrutinizer Quality Score](https://img.shields.io/scrutinizer/g/bzikarsky/gelf-php.svg?style=flat-square)](https://scrutinizer-ci.com/g/bzikarsky/gelf-php/)


A php implementation to send log-files to a gelf compatible backend like [Graylog2](http://graylog2.org/).
This library conforms to the PSR standards in regards to structure ([4](http://www.php-fig.org/psr/psr-4/)),
coding-style ([1](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md),
[2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md))
and logging ([3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md)).

It's a loosely based on the original [Graylog2 gelf-php](https://github.com/Graylog2/gelf-php)
and [mlehner's fork](https://github.com/mlehner/gelf-php).

Stable release and deprecation of the original graylog2/gelf-php
----------------------------------------------------------------

This implementation became the official PHP GELF library on 2013-12-19 and is now released as `graylog2/gelf-php`.
The old library became deprecated at the same time and it's recommended to upgrade.

Since the deprecated library never got a stable release, we decided keep it available as `v0.1`. This means:
If you have a project based on the deprecated library but no time to upgrade to version 1.0, we recommend to change your
`composer.json` as following:

        "require": {
           // ...
           "graylog2/gelf-php": "0.1.*"
           // ...
        }

After running an additional `composer update` everything should work as expected.

A note on PHP versions before 5.6
---------------------------------

I tried to keep backwards compatibility alive as long as possible, but it 2021 it's not feasible anymore to deal with the
pain of dependency management for PHP 5.3.3 - 5.5.latest. They are EOL for many years anyway.

**If you are somehow stuck on <5.6, you can use gelf-php up to version 1.6.5**.

I decided against a semver-compliant increase from 1.x to 2.x on purpose. 

Usage
-----

### Recommended installation via composer:

Add gelf-php to `composer.json` either by running `composer require graylog2/gelf-php` or by defining it manually:

    "require": {
       // ...
       "graylog2/gelf-php": "~1.5"
       // ...
    }

Reinstall dependencies: `composer install`

### Quick start

```php
use Gelf\Logger;
use Gelf\Publisher;
use Gelf\Transport\UdpTransport;

// Zero-config: logs to udp://localhost:12201
$logger = new Logger();
$logger->error('Something went wrong', ['user_id' => 42]);

// Or wire your own transport
$publisher = new Publisher(new UdpTransport('graylog.internal', 12201));
$logger = new Logger($publisher);
```

### Documentation

- [Architecture](docs/architecture.md) - Message, Publisher, Logger and how they fit together
- [Transports](docs/transports.md) - UDP, TCP, HTTP, AMQP and transport wrappers (retry, error silencing)
- [Monolog integration](docs/monolog.md) - standalone Monolog, Symfony and Laravel setup


License
-------

The library is licensed under the MIT license. See [LICENSE](LICENSE) for details.


Contributing
------------

See [CONTRIBUTING.md](CONTRIBUTING.md).
