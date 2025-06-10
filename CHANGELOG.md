# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Comprehensive documentation in markdown format
- GitHub Actions CI/CD pipeline with multi-version testing
- Unit tests for core functionality
- Code coverage reporting with Codecov
- PHP CS Fixer configuration for code style
- PHPStan configuration for static analysis
- Contributing guidelines
- Security best practices documentation

### Changed
- Updated composer.json to support PHP 7.4+ and Symfony 5.4+
- Enhanced configuration options for better flexibility
- Improved error handling and sanitization

### Security
- Added field sanitization for sensitive data
- Implemented secure RUM configuration
- Added best practices for production deployments

## [1.0.0] - 2024-01-01

### Added
- Initial release of Elastic APM Symfony Bundle
- Multiple APM backend support (Elastic APM, OpenTracing, Blackhole, Adaptive)
- Automatic instrumentation for:
  - HTTP requests and responses
  - Console commands
  - Symfony Messenger handlers
  - Database queries
  - Cache operations
  - Exceptions and errors
- Manual instrumentation API
- Distributed tracing support (W3C, B3, Jaeger)
- Transaction naming strategies
- Memory usage tracking
- RUM (Real User Monitoring) support
- Twig extensions for RUM integration
- Flexible configuration system
- Environment-based configuration
- Helper traits for common instrumentation patterns

### Security
- Secure by default configuration
- Optional RUM endpoint with security warnings
- Sensitive field sanitization
- Authentication support (secret token and API key)

[Unreleased]: https://github.com/yourvendor/elastic-apm-symfony-bundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/yourvendor/elastic-apm-symfony-bundle/releases/tag/v1.0.0