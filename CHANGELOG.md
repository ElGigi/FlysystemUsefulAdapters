# Change Log

All notable changes to this project will be documented in this file. This project adheres
to [Semantic Versioning] (http://semver.org/). For change log format,
use [Keep a Changelog] (http://keepachangelog.com/).

## [Unreleased]

### Added

- Suggest `psr/log` dependency, required to use the `LogAdapter`

### Fixed

- `FallbackAdapter` no longer throws `null` and returns the last result (even `false`) when every adapter answered, instead of re-throwing an exception raised by the last adapter

## [1.0.1] - 2024-03-20

### Fixed

- `FallbackAdapter::fileExists()` and `FallbackAdapter::directoryExists()` now try next adapter in cas of FALSE result

## [1.0.0] - 2024-03-14

Initial development
