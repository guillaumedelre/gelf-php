# Contributing

## Setup

```bash
git clone git@github.com:bzikarsky/gelf-php && cd gelf-php
composer install
```

## Running the checks locally

The CI pipeline runs three checks. Run them before opening a pull request.

**PHPUnit** (PHP 8.0, 8.1, 8.2 - lowest and highest dependencies):

```bash
vendor/bin/phpunit
```

**PHP_CodeSniffer** - [PSR-1][psr1] and [PSR-2][psr2] compliance across `src/`, `tests/` and `examples/`:

```bash
vendor/bin/phpcs --standard=PSR2 src tests examples
```

**Psalm** - static analysis on `src/` (PHP 8.2):

```bash
vendor/bin/psalm
```

## Code style

This project follows [PSR-1][psr1] and [PSR-2][psr2]. Run `phpcs` as shown above to verify before submitting. New code must target PHP >= 8.0.

## Questions and proposals

Open an issue or reach out to the maintainer on Twitter ([@bzikarsky](https://twitter.com/bzikarsky)).

## License

By contributing you agree that your changes will be licensed under the [MIT license](LICENSE).

[psr1]: https://www.php-fig.org/psr/psr-1/
[psr2]: https://www.php-fig.org/psr/psr-2/
