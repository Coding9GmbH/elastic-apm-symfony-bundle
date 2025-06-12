# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the Elastic APM Symfony Bundle developed by Coding9 GmbH. It provides comprehensive APM (Application Performance Monitoring) integration for Symfony applications with support for multiple backends including Elastic APM, OpenTracing (Jaeger/Zipkin), and distributed tracing.

## Development Commands

```bash
# Run all tests
composer test

# Run specific test suites
./vendor/bin/phpunit --testsuite=unit
./vendor/bin/phpunit --testsuite=functional

# Fix code style
composer cs-fix

# Run static analysis (PHPStan level 5)
composer phpstan

# Run tests with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Run a single test
./vendor/bin/phpunit tests/Unit/Interactor/ElasticApmInteractorTest.php
./vendor/bin/phpunit --filter testStartTransaction
```

## Architecture Overview

### Core Interactor Pattern

The bundle uses an **Interactor pattern** as the main abstraction layer. All APM operations go through `ElasticApmInteractorInterface`:

- **ElasticApmInteractor**: Default implementation using nipwaayoni/elastic-apm-php-agent library
- **OpenTracingInteractor**: For Jaeger/Zipkin via OpenTracing API (placeholder, needs implementation)
- **BlackholeInteractor**: No-op implementation for testing/disabled state
- **AdaptiveInteractor**: Runtime switching between implementations

**IMPORTANT**: The interface expects nipwaayoni library objects (Transaction, Span) as return types. All implementations must use or mock these types.

### Event-Driven Instrumentation

Automatic instrumentation is achieved through Symfony event listeners:

1. **RequestListener**: Tracks HTTP request lifecycle (kernel.request, kernel.response, kernel.finish_request)
2. **CommandListener**: Monitors console commands (console.command, console.terminate)
3. **MessengerListener**: Instruments message queue processing (all messenger events)
4. **ExceptionListener**: Captures unhandled exceptions
5. **MemoryUsageListener**: Optional memory tracking

### Transaction Naming Strategies

The bundle implements a Strategy pattern for transaction naming:

- **RouteNamingStrategy**: Uses Symfony route names (default)
- **ControllerNamingStrategy**: Uses controller class and method
- **UriNamingStrategy**: Uses request URI
- **ServiceNamingStrategy**: For non-HTTP contexts
- **MessageNamingStrategy**: For message queue handlers

### Service Configuration

Services are defined in XML files under `src/Resources/config/`:
- `services.xml`: Main services and bundle configuration
- `interactors.xml`: Interactor implementations
- `listeners.xml`: Event listeners
- `naming_strategies.xml`: Transaction naming strategies

### Distributed Tracing

The bundle supports multiple trace context propagation formats:
- W3C Trace Context (traceparent/tracestate headers)
- B3 Single and Multi headers (Zipkin)
- Jaeger (uber-trace-id)

## Key Design Decisions

1. **Configuration Through DI**: Uses `ConfigFactory` to build configuration from Symfony config and environment variables

2. **Messenger Integration**: Deep integration via:
   - `ApmMessageHandlerDecorator`: Wraps handlers for automatic tracking
   - `MessageHandlerApmTrait`: Helper methods for manual instrumentation

3. **Security by Default**:
   - RUM endpoint disabled by default (`expose_config_endpoint: false`)
   - Field sanitization for sensitive data
   - Warnings in configuration about security implications

4. **Performance Considerations**:
   - Adaptive interactor for conditional APM
   - Sampling configuration support
   - Blackhole interactor for zero overhead when disabled

## Testing Approach

- **Unit Tests**: Test individual components in isolation
- **Functional Tests**: Test bundle integration with Symfony
- PHPUnit configuration in `phpunit.xml.dist`
- Tests organized under `tests/Unit/` and `tests/Functional/`

## Configuration Processing

The bundle uses Symfony's Configuration component with:
- Tree builder in `DependencyInjection/Configuration.php`
- Extension loading in `DependencyInjection/ElasticApmExtension.php`
- Environment variable support with `%env()%` processors

## Important Files to Understand

1. **ElasticApmInteractorInterface.php**: Core contract for all APM operations
2. **RequestListener.php**: Main HTTP instrumentation logic
3. **Configuration.php**: All available configuration options
4. **ElasticApmExtension.php**: Bundle initialization and service registration