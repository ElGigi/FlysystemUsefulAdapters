# Change Log

All notable changes to this project will be documented in this file. This project adheres
to [Semantic Versioning] (http://semver.org/). For change log format,
use [Keep a Changelog] (http://keepachangelog.com/).

## [Unreleased]

### Added

- `IgnoreFilesystemAdapter` filtering entries based on `.gitignore`-style ignore files (cascade, negation, `**`, directory-only), with a `$strict` mode and support for multiple ignore filenames
- `RetryAdapter` now supports a `$multiplier` argument enabling exponential backoff between attempts
- Suggest `psr/log` dependency, required to use the `LogAdapter`

### Fixed

- `FallbackAdapter` no longer throws `null` and returns the last result (even `false`) when every adapter answered, instead of re-throwing an exception raised by the last adapter
- `RetryAdapter` now validates its `retry` (>= 1) and `time` (>= 0) constructor arguments and no longer sleeps after the last attempt
- `ReadWriteAdapter` now requires at least one reader and one writer adapter, preventing a `null` exception when a list is empty
- `LogAdapter` removed unreachable dead code in the log level resolution

## [1.0.1] - 2024-03-20

### Fixed

- `FallbackAdapter::fileExists()` and `FallbackAdapter::directoryExists()` now try next adapter in cas of FALSE result

## [1.0.0] - 2024-03-14

Initial development
