# Contributing to Elastic APM Symfony Bundle

Thank you for your interest in contributing to the Elastic APM Symfony Bundle! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How to Contribute](#how-to-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Documentation](#documentation)
- [Submitting Changes](#submitting-changes)
- [Release Process](#release-process)

## Code of Conduct

This project adheres to a Code of Conduct that all contributors are expected to follow. Please be respectful and professional in all interactions.

## How to Contribute

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include:

- A clear and descriptive title
- Steps to reproduce the issue
- Expected vs actual behavior
- Environment details (PHP version, Symfony version, etc.)
- Stack traces and error messages
- Code samples demonstrating the issue

### Suggesting Enhancements

Enhancement suggestions are welcome! Please include:

- A clear and descriptive title
- Detailed description of the proposed feature
- Use cases and benefits
- Possible implementation approach
- Any potential drawbacks

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Update documentation
7. Commit your changes
8. Push to your fork
9. Open a Pull Request

## Development Setup

### Prerequisites

- PHP 7.4 or higher
- Composer 2.x
- Docker (optional, for testing multiple PHP versions)

### Local Development

1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourvendor/elastic-apm-symfony-bundle.git
   cd elastic-apm-symfony-bundle
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up pre-commit hooks:**
   ```bash
   cp .git/hooks/pre-commit.sample .git/hooks/pre-commit
   chmod +x .git/hooks/pre-commit
   ```

   Add to `.git/hooks/pre-commit`:
   ```bash
   #!/bin/sh
   composer cs-fix
   composer phpstan
   composer test
   ```

### Docker Development

```bash
# Build development container
docker build -t apm-bundle-dev .

# Run tests in container
docker run --rm -v $(pwd):/app apm-bundle-dev composer test

# Interactive development
docker run --rm -it -v $(pwd):/app apm-bundle-dev bash
```

## Coding Standards

### PHP Standards

We follow Symfony coding standards with some additions:

- PSR-12 coding style
- Strict types declaration in all PHP files
- Type hints for all parameters and return types
- Meaningful variable and method names

### Code Style

Run PHP CS Fixer before committing:

```bash
composer cs-fix
```

Configuration is in `.php-cs-fixer.dist.php`.

### Static Analysis

Run PHPStan to catch potential issues:

```bash
composer phpstan
```

Level 5 is required for all code.

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit --testsuite=unit
./vendor/bin/phpunit --testsuite=functional

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Writing Tests

#### Unit Tests

```php
namespace ElasticApmBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testSomething(): void
    {
        // Arrange
        $service = new MyService();
        
        // Act
        $result = $service->doSomething();
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

#### Functional Tests

```php
namespace ElasticApmBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BundleIntegrationTest extends KernelTestCase
{
    public function testServiceRegistration(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        $this->assertTrue($container->has('elastic_apm.interactor'));
    }
}
```

### Test Coverage

Maintain test coverage above 80%:

```bash
# Generate coverage report
composer test-coverage

# View coverage
open coverage/index.html
```

## Documentation

### Inline Documentation

All public methods must have PHPDoc:

```php
/**
 * Starts a new APM transaction.
 *
 * @param string $name Transaction name (e.g., "GET /products")
 * @param string $type Transaction type (e.g., "request", "cli")
 *
 * @return Transaction The started transaction
 *
 * @throws ApmException If APM is not properly configured
 */
public function startTransaction(string $name, string $type): Transaction
{
    // Implementation
}
```

### Documentation Updates

When adding features or changing behavior:

1. Update relevant markdown files in `docs/`
2. Update README.md if needed
3. Add examples to documentation
4. Update configuration reference

### Documentation Style

- Use clear, concise language
- Include code examples
- Explain the "why" not just the "how"
- Keep formatting consistent

## Submitting Changes

### Commit Messages

Follow conventional commits format:

```
feat: add support for custom span types
fix: handle null transaction in span creation
docs: update installation guide
test: add tests for message handler
refactor: simplify interactor factory
```

### Pull Request Process

1. **Create PR with descriptive title:**
   ```
   feat: Add OpenTracing support for Jaeger backend
   ```

2. **Fill out PR template:**
   - Description of changes
   - Related issues
   - Testing performed
   - Breaking changes
   - Checklist completion

3. **Ensure CI passes:**
   - All tests must pass
   - Code style must be correct
   - PHPStan must pass
   - Coverage must not decrease

4. **Address review feedback:**
   - Respond to all comments
   - Make requested changes
   - Re-request review when ready

### PR Checklist

- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] No breaking changes (or clearly marked)
- [ ] CI passes
- [ ] Code follows style guide
- [ ] Commits are clean and logical

## Release Process

### Version Numbering

We follow Semantic Versioning (SemVer):

- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes

### Release Steps

1. **Update CHANGELOG.md:**
   ```markdown
   ## [1.2.0] - 2024-01-15
   
   ### Added
   - OpenTracing support for Jaeger
   - Custom naming strategies
   
   ### Fixed
   - Memory leak in span collection
   
   ### Changed
   - Improved transaction naming
   ```

2. **Update version constraints:**
   - composer.json
   - Documentation

3. **Create release tag:**
   ```bash
   git tag -a v1.2.0 -m "Release version 1.2.0"
   git push origin v1.2.0
   ```

4. **Create GitHub release:**
   - Use tag
   - Copy CHANGELOG entries
   - Attach any binaries

### Backward Compatibility

- Maintain BC within major versions
- Deprecate before removing
- Document migration paths

## Development Tips

### Local APM Server

Run local APM server for testing:

```bash
docker run -d \
  -p 8200:8200 \
  --name=apm-server \
  docker.elastic.co/apm/apm-server:8.11.0
```

### Debugging

Enable debug mode:

```yaml
elastic_apm:
    logging: true
    debug:
        enabled: true
        log_level: debug
```

### Performance Testing

```php
class PerformanceTest extends TestCase
{
    public function testPerformance(): void
    {
        $start = microtime(true);
        
        // Your code
        
        $duration = microtime(true) - $start;
        $this->assertLessThan(0.01, $duration, 'Operation too slow');
    }
}
```

## Getting Help

- Create an issue for bugs or features
- Join discussions for questions
- Check existing issues and PRs
- Read the documentation

Thank you for contributing! 🎉