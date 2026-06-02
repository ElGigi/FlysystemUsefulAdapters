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

The `FallbackAdapter` adapter allow to write or read on a fallback adapter.

Imagine that your main adapter is a S3 in an unavailable region, to continue to receive files from your customers, you
can use a fallback adapter on another region.

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

The `LogAdapter` is compliant with `psr/log`, and allow to log actions on file systems.

### ReadWriteAdapter

The `ReadWriteAdapter` adapter allow to separate readers and writers adapters.

### RetryAdapter

The `RetryAdapter` adapter allow to retry an action on file system in case of failure, after a delay and X times.
