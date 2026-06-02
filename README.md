# FlysytemUsefulAdapters

[![Latest Version](http://img.shields.io/packagist/v/elgigi/flysystem-useful-adapters.svg?style=flat-square)](https://github.com/ElGigi/FlysystemUsefulAdapters/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/ElGigi/FlysystemUsefulAdapters/tests.yml?branch=main&style=flat-square)](https://github.com/ElGigi/FlysystemUsefulAdapters/actions/workflows/tests.yml?query=branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/elgigi/flysystem-useful-adapters.svg?style=flat-square)](https://packagist.org/packages/elgigi/flysystem-useful-adapters)

This extension adds some useful adapters for the [`league/flysystem`](https://github.com/thephpleague/flysystem) library.

## Installation

You can install the client with [Composer](https://getcomposer.org/):

```bash
composer require elgigi/flysystem-useful-adapters
```

## Adapters

### FallbackAdapter

The `FallbackAdapter` allows you to read from or write to a fallback adapter when the primary one fails.

Imagine that your main adapter is an S3 bucket in an unavailable region; to continue to receive files from your
customers, you can use a fallback adapter on another region.

Adapters are tried in the order they are given until one succeeds. If every adapter throws, the last exception is
rethrown.

```php
use ElGigi\FlysystemUsefulAdapters\FallbackAdapter;
use League\Flysystem\Filesystem;

// First argument is the primary adapter, the following ones are fallbacks (tried in order)
$adapter = new FallbackAdapter($primaryAdapter, $fallbackAdapter, $anotherFallbackAdapter);

$fs = new Filesystem($adapter);
```

### IgnoreFilesystemAdapter

The `IgnoreFilesystemAdapter` decorates an adapter and filters entries based on `.gitignore`-style ignore files
(e.g. `.docignore`). Patterns follow the gitignore syntax (negation with `!`, anchoring with `/`, `**`,
directory-only with a trailing `/`, character classes…) and are resolved as a cascade: an ignore file placed in a
subdirectory applies to that subtree, overriding the rules of parent directories.

```php
use ElGigi\FlysystemUsefulAdapters\IgnoreFilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$inner = new LocalFilesystemAdapter('/path');

// Read rules from .docignore files
$adapter = new IgnoreFilesystemAdapter($inner, '.docignore');

// Several ignore filenames can be provided (rules are concatenated in order)
$adapter = new IgnoreFilesystemAdapter($inner, ['.docignore', '.exportignore']);

$fs = new Filesystem($adapter);
```

The third argument `$strict` (default `true`) controls how ignored paths behave:

- `$strict = true`: an ignored path is treated as if it does not exist for **every** operation
  (`read`/`write`/metadata throw the relevant exception, `fileExists` returns `false`, `delete` is a no-op).
- `$strict = false`: only `listContents()` is filtered; direct accesses are passed through to the inner adapter.

```php
$adapter = new IgnoreFilesystemAdapter($inner, '.docignore', strict: false);
```

The ignore files themselves are always hidden from `listContents()` but remain readable.

### LogAdapter

The `LogAdapter` is compliant with `psr/log` and allows you to log actions performed on a filesystem.

Each operation is logged with a level that depends on its nature: write-type operations (`write`, `writeStream`,
`copy`, `move`, `delete`, `deleteDirectory`, `createDirectory`, `setVisibility`) are logged at the `notice` level,
read and metadata operations at the `debug` level, and any failure at the `error` level.

```php
use ElGigi\FlysystemUsefulAdapters\LogAdapter;
use League\Flysystem\Filesystem;

// $logger is any PSR-3 Psr\Log\LoggerInterface implementation
$adapter = new LogAdapter($innerAdapter, $logger);

// The logger can also be injected later via setLogger()
$adapter = new LogAdapter($innerAdapter);
$adapter->setLogger($logger);

$fs = new Filesystem($adapter);
```

### ReadWriteAdapter

The `ReadWriteAdapter` allows you to separate reader and writer adapters.

Write methods (`write`, `writeStream`, `delete`, `copy`, `move`, `createDirectory`, `deleteDirectory`,
`setVisibility`) are routed to the writer adapters, while all other (read/metadata) methods are routed to the reader
adapters. Readers are automatically wrapped as read-only to ensure no write happens through them. At least one reader
and one writer are required.

```php
use ElGigi\FlysystemUsefulAdapters\ReadWriteAdapter;
use League\Flysystem\Filesystem;

$adapter = new ReadWriteAdapter(
    readers: [$readReplicaAdapter],
    writers: [$primaryAdapter],
);

$fs = new Filesystem($adapter);
```

### RetryAdapter

The `RetryAdapter` allows you to retry an action on a filesystem in case of failure, after a delay and a given number
of times.

The second argument `$time` is the delay between attempts, in **milliseconds** (default `5000`). The third argument
`$retry` is the total number of attempts (default `2`, minimum `1`). The fourth argument `$multiplier` (default `1.0`,
minimum `1`) applies an exponential backoff: the delay for attempt `n` is `time * multiplier^n` (e.g. with
`time: 1000` and `multiplier: 2.0`, delays are 1000 ms, then 2000 ms, then 4000 ms…). A multiplier of `1.0` keeps a
constant delay. No delay is applied after the last attempt; if all attempts fail, the last exception is rethrown.

```php
use ElGigi\FlysystemUsefulAdapters\RetryAdapter;
use League\Flysystem\Filesystem;

// Retry up to 3 times, waiting 1000 ms between attempts
$adapter = new RetryAdapter($innerAdapter, time: 1000, retry: 3);

// Exponential backoff: 1000 ms, then 2000 ms between attempts
$adapter = new RetryAdapter($innerAdapter, time: 1000, retry: 3, multiplier: 2.0);

$fs = new Filesystem($adapter);
```
