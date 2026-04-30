# Changelog

All notable changes to `laravel-schema-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.12] - 2026-04-30

### Fixed

- TypeScript type alias name no longer gets a spurious `Type` inserted mid-name when the schema name itself contains the substring `Schema` (e.g. `UpdateLeadFieldSchemaRequestSchema` previously emitted `UpdateLeadFieldSchemaTypeRequestSchemaType` instead of `UpdateLeadFieldSchemaRequestSchemaType`). The replacement is now anchored to the trailing `Schema` suffix.

## [1.0.11] - 2026-04-24

### Fixed

- Compatibility with `spatie/laravel-data` 4.22.0, which added a new `DataClassFromValidationPayloadResolver` argument to `DataValidationRulesResolver` and widened `DataValidationMessagesAndAttributesResolver`'s constructor. The inheriting resolvers no longer redeclare pass-through constructors and are now registered via DI binding so they auto-resolve whichever parent signature is installed (still compatible with 3.x / earlier 4.x).

## [1.0.0] - 2025-09-XX

### Added

- Initial release
- Support for Laravel FormRequest classes
- Support for Spatie Laravel Data classes (when installed)
- Support for any PHP class with a `rules()` method
- `#[ValidationSchema]` attribute for marking classes to generate schemas
- `#[InheritValidationFrom]` attribute for reusing validation rules
- Smart package detection for optional features
- Automatic integration with `typescript:transform` command
- Configurable output paths and formats
- Custom error message support
- Enum validation support
- Array item validation support
- Comprehensive test suite
- Extensible architecture with custom extractors
