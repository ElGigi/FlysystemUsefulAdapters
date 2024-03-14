# FlysytemUsefulAdapters

This extension adds some useful adapters for the [`league/flysystem`](https://github.com/thephpleague/flysystem) library.

## Installation

You can install the client with [Composer](https://getcomposer.org/):

```bash
composer require elgigi/flysystem-usefull-adapters
```

## Adapters

### FallbackAdapter

The `FallbackAdapter` adapter allow to write or read on a fallback adapter.

Imagine that your main adapter is a S3 in an unavailable region, to continu to receive files from your customers, you
can use a fallback adapter on another region.

### LogAdapter

The `LogAdapter` is compliant with `psr/log`, and allow to log actions on file systems.

### ReadWriteAdapter

The `ReadWriteAdapter` adapter allow to separate readers and writers adapters.

### RetryAdapter

The `RetryAdapter` adapter allow to retry an action on file system in case of failure, after a delay and X times.
