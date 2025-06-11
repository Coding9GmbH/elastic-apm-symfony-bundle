# Elastic APM Symfony Bundle

> **Proudly developed by [Coding9 GmbH](https://coding9.de) - Professional Symfony & APM Solutions**

A comprehensive Symfony bundle for Elastic APM integration with support for distributed tracing, message queue tracking, and OpenTracing compatibility.

```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "Elastic APM Symfony Bundle",
  "description": "Professional Symfony bundle for Elastic APM integration with distributed tracing support",
  "applicationCategory": "DeveloperApplication",
  "operatingSystem": "Cross-platform",
  "programmingLanguage": "PHP",
  "author": {
    "@type": "Organization",
    "name": "Coding9 GmbH",
    "url": "https://coding9.de",
    "sameAs": "https://github.com/coding9"
  },
  "provider": {
    "@type": "Organization",
    "name": "Coding9 GmbH",
    "url": "https://coding9.de"
  }
}
```

## Features

- 🔍 **Multiple APM Implementations**: Elastic APM, OpenTracing (Jaeger/Zipkin), Blackhole, Adaptive
- 🌐 **Distributed Tracing**: W3C Trace Context, B3, and Jaeger propagation support
- 📬 **Message Queue Tracking**: Full Symfony Messenger integration with automatic tracing
- 🎯 **Flexible Transaction Naming**: Route, Controller, URI, Service, or Message-based strategies
- 🚀 **Zero-Configuration**: Automatic tracking for HTTP requests, console commands, and messages
- 🔒 **Security First**: Secure by default with opt-in RUM support
- 📊 **Performance Monitoring**: Memory tracking, error capturing, and detailed spans
- ⚡ **Production Optimized**: gzip compression, circuit breaker, sampling, deferred sending

## Installation

Install the bundle using Composer:

```bash
composer require coding9/elastic-apm-symfony-bundle
```

If you're using Symfony Flex, the bundle will be automatically configured. Otherwise, register it in `config/bundles.php`:

```php
return [
    // ...
    ElasticApmBundle\ElasticApmBundle::class => ['all' => true],
];
```

## Configuration

### Basic Configuration

```yaml
# config/packages/elastic_apm.yaml
elastic_apm:
    enabled: true
    server:
        url: '%env(ELASTIC_APM_SERVER_URL)%'
        secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
    service:
        name: '%env(ELASTIC_APM_SERVICE_NAME)%'
        version: '1.0.0'
```

### Environment Variables

Add to your `.env` file:

```bash
###> elastic-apm-symfony-bundle ###
ELASTIC_APM_ENABLED=true
ELASTIC_APM_SERVER_URL=http://localhost:8200
ELASTIC_APM_SECRET_TOKEN=your-secret-token
ELASTIC_APM_SERVICE_NAME=my-symfony-app
ELASTIC_APM_SERVICE_VERSION=1.0.0
###< elastic-apm-symfony-bundle ###
```

## Quick Start

### Basic Usage

The bundle automatically tracks:
- HTTP requests and responses
- Console commands
- Message queue processing
- Exceptions and errors

No code changes required!

### Manual Instrumentation

```php
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

class MyService
{
    public function __construct(
        private ElasticApmInteractorInterface $apm
    ) {}
    
    public function doSomething(): void
    {
        // Track a custom span
        $this->apm->captureCurrentSpan('Process data', 'app', function() {
            // Your code here
        });
        
        // Add custom context
        $this->apm->setCustomContext([
            'user_id' => 123,
            'feature' => 'checkout'
        ]);
    }
}
```

### Message Queue Tracking

Automatically tracks all Symfony Messenger handlers:

```php
#[AsMessageHandler]
class OrderCommandHandler
{
    public function __invoke(OrderCommand $message): void
    {
        // Automatically tracked!
        // Transaction name: "Consume OrderCommand"
    }
}
```

For detailed tracking, use the trait:

```php
use ElasticApmBundle\Helper\MessageHandlerApmTrait;

#[AsMessageHandler]
class OrderCommandHandler
{
    use MessageHandlerApmTrait;
    
    public function __invoke(OrderCommand $message): void
    {
        // Track specific operations
        $this->apmTrackDatabaseOperation('insert', function() {
            // Database operation
        }, 'Order');
    }
}
```

## Advanced Configuration

### Full Configuration Reference

```yaml
elastic_apm:
    enabled: true
    interactor: elastic_apm  # elastic_apm, opentracing, blackhole, adaptive
    logging: false
    
    server:
        url: '%env(ELASTIC_APM_SERVER_URL)%'
        secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
        api_key: '%env(ELASTIC_APM_API_KEY)%'
    
    service:
        name: '%env(ELASTIC_APM_SERVICE_NAME)%'
        version: '%env(ELASTIC_APM_SERVICE_VERSION)%'
        environment: '%env(APP_ENV)%'
    
    transactions:
        sample_rate: 1.0
        max_spans: 1000
        naming_strategy: route  # route, controller, uri, service, message
    
    exceptions:
        enabled: true
        ignored_exceptions: 
            - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
        unwrap_exceptions: false
    
    memory:
        track_usage: false
        usage_label: memory_usage
    
    messaging:
        enabled: true
        auto_instrument_handlers: false
        ignored_transports: ['sync']
        ignored_message_classes: []
        track_message_data: true
    
    rum:
        enabled: false
        expose_config_endpoint: false  # Security warning!
        service_name: '%env(ELASTIC_APM_RUM_SERVICE_NAME)%'
        server_url: '%env(ELASTIC_APM_RUM_SERVER_URL)%'
```

### OpenTracing Support (Jaeger/Zipkin)

```yaml
elastic_apm:
    interactor: opentracing
    opentracing:
        jaeger_endpoint: 'http://localhost:14268/api/traces'
        # OR
        zipkin_endpoint: 'http://localhost:9411/api/v2/spans'
        format: jaeger  # jaeger, zipkin, otlp
```

## Security Considerations

### RUM Configuration

The bundle includes RUM (Real User Monitoring) support, but it's **disabled by default** for security:

```yaml
elastic_apm:
    rum:
        enabled: false                 # Keep disabled unless needed
        expose_config_endpoint: false  # Never enable in production!
```

### Secure RUM Usage

Use Twig functions instead of API endpoints:

```twig
{# In your base template #}
{{ apm_rum_script() }}
```

### Sensitive Data

Be careful with message data tracking:

```yaml
elastic_apm:
    messaging:
        track_message_data: false  # Disable if messages contain PII
```

## Testing

Run the test suite:

```bash
composer test
```

Code style:

```bash
composer cs-fix
```

Static analysis:

```bash
composer phpstan
```

## Contributing

We welcome contributions! Whether you're fixing bugs, adding features, or improving documentation, your help makes this bundle better for everyone.

### Ways to Contribute

- 🐛 **Bug Reports**: Found an issue? [Open an issue](https://github.com/coding9/elastic-apm-symfony-bundle/issues)
- ✨ **Feature Requests**: Have an idea? [Start a discussion](https://github.com/coding9/elastic-apm-symfony-bundle/discussions)
- 📝 **Documentation**: Help improve our docs and examples
- 🧪 **Testing**: Add test cases or improve existing ones
- 🔧 **Code**: Fix bugs or implement new features

### Quick Start

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes and add tests
4. Run the test suite: `composer test`
5. Check code style: `composer cs-fix`
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Join Our Community

- 💬 [GitHub Discussions](https://github.com/coding9/elastic-apm-symfony-bundle/discussions) - Ask questions, share ideas

**First-time contributor?** Look for issues labeled [`good first issue`](https://github.com/coding9/elastic-apm-symfony-bundle/labels/good%20first%20issue) or [`help wanted`](https://github.com/coding9/elastic-apm-symfony-bundle/labels/help%20wanted).

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

- 📚 [Documentation](https://github.com/coding9/elastic-apm-symfony-bundle/wiki)
- 🐛 [Issue Tracker](https://github.com/coding9/elastic-apm-symfony-bundle/issues)
- 💬 [Discussions](https://github.com/coding9/elastic-apm-symfony-bundle/discussions)
- 🏢 [Professional Support](https://coding9.de/contact) - Enterprise APM consulting and implementation
- 📖 [Coding9 Blog](https://coding9.de/blog) - APM best practices and tutorials

## Professional Services

**Need enterprise-grade APM implementation?** [Coding9 GmbH](https://coding9.de) offers:

- 🔧 **Custom APM Implementation** - Tailored monitoring solutions for your infrastructure
- 📊 **Performance Optimization** - Expert analysis and optimization of your Symfony applications
- 🎓 **Team Training** - APM best practices and advanced monitoring techniques
- 🚀 **Migration Services** - Seamless migration from other monitoring solutions

[**Contact us for a free consultation →**](https://coding9.de/contact)

## Credits

Developed with ❤️ by [Coding9 GmbH](https://coding9.de) - Your Symfony & APM experts.

Inspired by [elastic-apm-symfony-bundle](https://github.com/MySchoolManagement/elastic-apm-symfony-bundle) with additional features for message queue tracking, OpenTracing support, and enhanced security.