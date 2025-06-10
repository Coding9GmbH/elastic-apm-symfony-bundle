# Elastic APM Symfony Bundle Documentation

Welcome to the comprehensive documentation for the Elastic APM Symfony Bundle. This bundle provides seamless integration between Symfony applications and various APM (Application Performance Monitoring) solutions.

## Table of Contents

### Getting Started
- [Installation](getting-started/installation.md) - How to install and configure the bundle
- [Quick Start](getting-started/quick-start.md) - Get up and running in 5 minutes
- [Requirements](getting-started/requirements.md) - System and dependency requirements

### Configuration
- [Basic Configuration](configuration/basic.md) - Essential configuration options
- [Advanced Configuration](configuration/advanced.md) - Full configuration reference
- [Environment Variables](configuration/environment-variables.md) - Using environment variables
- [Multiple Environments](configuration/multiple-environments.md) - Dev, staging, and production setup

### Usage
- [Automatic Instrumentation](usage/automatic-instrumentation.md) - What's tracked automatically
- [Manual Instrumentation](usage/manual-instrumentation.md) - Adding custom tracking
- [Message Queue Tracking](usage/message-queue-tracking.md) - Symfony Messenger integration
- [Console Commands](usage/console-commands.md) - Tracking CLI commands
- [Error Tracking](usage/error-tracking.md) - Exception and error monitoring

### API Reference
- [Interactor Interface](api/interactor-interface.md) - Core APM interface
- [Helper Traits](api/helper-traits.md) - Convenient traits for common tasks
- [Events](api/events.md) - Available events and hooks
- [Naming Strategies](api/naming-strategies.md) - Transaction naming customization

### Security
- [Best Practices](security/best-practices.md) - Security recommendations
- [RUM Configuration](security/rum-configuration.md) - Secure Real User Monitoring setup
- [Sensitive Data](security/sensitive-data.md) - Handling PII and secrets

### Advanced Topics
- [OpenTracing Support](advanced/opentracing.md) - Jaeger and Zipkin integration
- [Distributed Tracing](advanced/distributed-tracing.md) - Trace context propagation
- [Performance Optimization](advanced/performance.md) - Optimizing APM overhead
- [Custom Interactors](advanced/custom-interactors.md) - Building custom APM adapters
- [Troubleshooting](advanced/troubleshooting.md) - Common issues and solutions

## Quick Links

- [GitHub Repository](https://github.com/yourvendor/elastic-apm-symfony-bundle)
- [Issue Tracker](https://github.com/yourvendor/elastic-apm-symfony-bundle/issues)
- [Elastic APM Documentation](https://www.elastic.co/guide/en/apm/agent/php/current/index.html)
- [Symfony Documentation](https://symfony.com/doc/current/index.html)

## Contributing

We welcome contributions! Please see our [Contributing Guide](../CONTRIBUTING.md) for details.

## License

This bundle is released under the MIT License. See the [LICENSE](../LICENSE) file for details.