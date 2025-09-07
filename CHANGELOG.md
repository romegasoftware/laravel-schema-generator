# Changelog

All notable changes to `laravel-zod-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-09-XX

### Added

- Initial release
- Support for Laravel FormRequest classes
- Support for Spatie Laravel Data classes (when installed)
- Support for any PHP class with a `rules()` method
- `#[ZodSchema]` attribute for marking classes to generate schemas
- `#[InheritValidationFrom]` attribute for reusing validation rules
- Smart package detection for optional features
- Automatic integration with `typescript:transform` command
- Configurable output paths and formats
- Custom error message support
- Enum validation support
- Array item validation support
- Comprehensive test suite
- Extensible architecture with custom extractors
