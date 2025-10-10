# Changelog

All notable changes to `flare-client-php` will be documented in this file

## 2.3.1 - 2025-10-10

- Better handling of success codes in the Api

## 2.3.0 - 2025-10-08

- Fixed an issue where disabled tracing could still trace
- Completely refactored the recorders, the old traits are now deprecated in favour of specific classes for spans or span events
- Fix an issue where attributes of spans & span events used in an error removed the span type

## 2.2.3 - 2025-10-06

- Fix sampler

## 2.2.2 - 2025-09-11

- Fix missing interface parameter

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.2.0...2.2.2

## 2.2.0 - 2025-09-11

### What's Changed

* Add additional methods to control custom context

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.1.0...2.2.0

## 2.1.0 - 2025-08-27

- Add support for stacktraces with logs

## 2.0.9 - 2025-08-25

- Laravel's `artisan` is now considered a vendor file in stacktraces
- Statamic's `please` is now considered a vendor file in stacktraces

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.0.8...2.0.9

## 2.0.8 - 2025-08-21

### What's Changed

* Fix issue where dns servers were contacted by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/44

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.0.7...2.0.8

## 2.0.7 - 2025-07-17

- Add support for Curl Options

## 2.0.6 - 2025-06-24

- Add support for disabled Flare

## 2.0.5 - 2025-06-03

- Add base path to stacktrace builder

## 2.0.3 - 2025-05-21

- Use base URL const

## 2.0.2 - 2025-05-21

- Use correct base url

## 2.0.1 - 2025-05-21

- Routing changes

## 2.0.0 - 2025-05-20

A complete rewrite of the package:

- You can read our new docs on what has changed
- There's an upgrade guide available

## 1.10.1 - 2025-02-14

### What's Changed

* Supports Laravel 12 by @duncanmcclean in https://github.com/spatie/flare-client-php/pull/38

### New Contributors

* @duncanmcclean made their first contribution in https://github.com/spatie/flare-client-php/pull/38

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.10.0...1.10.1

## 1.10.0 - 2024-12-02

- Add support for overriding grouping

## 1.9.0 - 2024-12-02

### What's Changed

* Fix PHP 8.4 deprecation errors by @Nationalcat in https://github.com/spatie/flare-client-php/pull/33
* Fix implicitly nullable parameters for PHP 8.4 by @maximal in https://github.com/spatie/flare-client-php/pull/34

### New Contributors

* @Nationalcat made their first contribution in https://github.com/spatie/flare-client-php/pull/33
* @maximal made their first contribution in https://github.com/spatie/flare-client-php/pull/34

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.8.0...1.9.0

## 1.8.0 - 2024-08-01

- Fix issues with symphony request payloads not behaving like they should.

## 1.7.0 - 2024-06-12

### What's Changed

* Refactor solutions by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/30

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.6.0...1.7.0

## 1.6.0 - 2024-05-22

### What's Changed

* Fix incorrect argument order when calling previous error handler by @RasmusStahl in https://github.com/spatie/flare-client-php/pull/28

### New Contributors

* @RasmusStahl made their first contribution in https://github.com/spatie/flare-client-php/pull/28

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.5.1...1.6.0

## 1.5.1 - 2024-05-03

### What's Changed

* Feature/configurable error levels by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/27

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.5.0...1.5.1

## 1.5.0 - 2024-05-02

### What's Changed

* Add support for handling errors by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/26

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.4.4...1.5.0

## 1.4.4 - 2024-01-31

### What's Changed

* Drop Carbon dependency from composer.json by @jnoordsij in https://github.com/spatie/flare-client-php/pull/23

### New Contributors

* @jnoordsij made their first contribution in https://github.com/spatie/flare-client-php/pull/23

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.4.3...1.4.4

## 1.4.3 - 2023-10-17

### What's Changed

- [1.x] Tests against PHP 8.3 and adds Symfony 7 support by @nunomaduro in https://github.com/spatie/flare-client-php/pull/19

### New Contributors

- @nunomaduro made their first contribution in https://github.com/spatie/flare-client-php/pull/19

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.4.2...1.4.3

## 1.4.2 - 2023-07-28

- Loosen type for previous exception handler

## 1.4.1 - 2023-07-06

- Add better support for error exceptions

## 1.4.0 - 2023-06-28

- Add support for stack trace arguments

## 1.3.6 - 2023-04-12

- recognise AI generated solutions

## 1.3.5 - 2023-01-23

### What's Changed

- Update composer.json by @driesvints in https://github.com/spatie/flare-client-php/pull/3
- Prepare for Laravel 10 by @freekmurze in https://github.com/spatie/flare-client-php/pull/13

### New Contributors

- @driesvints made their first contribution in https://github.com/spatie/flare-client-php/pull/3

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.2...1.3.5

## 1.3.4 - 2023-01-23

- prep for Laravel 10

## 1.3.3 - 2023-01-23

- prepare for Laravel 10

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.2...1.3.3

## 1.3.2 - 2022-12-26

- reset glows

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.1...1.3.2

## 1.3.1 - 2022-11-16

### What's Changed

- Bug: Correct report sending logic by @Jellyfrog in https://github.com/spatie/flare-client-php/pull/11

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.0...1.3.1

## 1.3.0 - 2022-08-08

### What's Changed

- Allow `reportErrorLevels` to be 0 by @Jellyfrog in https://github.com/spatie/flare-client-php/pull/9
- Add support for filtering reports before sending to Flare by @Jellyfrog in https://github.com/spatie/flare-client-php/pull/10

### New Contributors

- @Jellyfrog made their first contribution in https://github.com/spatie/flare-client-php/pull/9

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.2.0...1.3.0

## 1.2.0 - 2022-05-16

- Add `php_version` as default `env` context

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.1.1...1.2.0

## 1.1.1 - 2022-05-11

## What's Changed

- Flat map request headers for PSR request context (47302e83d1b212ebc682bd18d7c27b8027db6c4e)
- Update .gitattributes by @angeljqv in https://github.com/spatie/flare-client-php/pull/6

## New Contributors

- @angeljqv made their first contribution in https://github.com/spatie/flare-client-php/pull/6

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.1.0...1.1.1

## 1.1.0 - 2022-03-11

- Allow passing an initialised `Report` instance to `report()` to Flare

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.5...1.1.0

## 1.0.5 - 2022-03-01

- Fix exception when `stage` is `null`

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.4...1.0.5

## 1.0.4 - 2022-02-28

## What's Changed

- catch throwable instead of exception for getSession failure by @ZeoKnight in https://github.com/spatie/flare-client-php/pull/5

## New Contributors

- @ZeoKnight made their first contribution in https://github.com/spatie/flare-client-php/pull/5

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.3...1.0.4

## 1.0.3 - 2022-02-25

## What's Changed

- Remove `arguments` from stacktrace frames (unused in UI and causing issues, see https://github.com/spatie/ignition/issues/48)
- Update .gitattributes by @PaolaRuby in https://github.com/spatie/flare-client-php/pull/4

## New Contributors

- @PaolaRuby made their first contribution in https://github.com/spatie/flare-client-php/pull/4

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.2...1.0.3

## 1.0.2 - 2022-02-16

- avoid crash git info middleware

## 1.0.1 - 2022-02-04

- Add censor request headers middleware

## 1.0.0 - 2022-01-18

- initial release
