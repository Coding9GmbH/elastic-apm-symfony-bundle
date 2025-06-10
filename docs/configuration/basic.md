# Basic Configuration

This guide covers the essential configuration options to get you started with the Elastic APM Symfony Bundle.

## Minimal Configuration

The absolute minimum configuration requires only these settings:

```yaml
# config/packages/elastic_apm.yaml
elastic_apm:
    enabled: true
    service_name: 'my-app'
    server_url: 'http://localhost:8200'
```

## Common Configuration Options

### Service Configuration

```yaml
elastic_apm:
    # Enable/disable APM
    enabled: '%env(bool:ELASTIC_APM_ENABLED)%'
    
    # Service identification
    service_name: '%env(ELASTIC_APM_SERVICE_NAME)%'
    service_version: '%env(ELASTIC_APM_SERVICE_VERSION)%'
    environment: '%env(APP_ENV)%'
    
    # APM Server connection
    server_url: '%env(ELASTIC_APM_SERVER_URL)%'
    secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
    # OR use API key instead of secret token
    api_key: '%env(ELASTIC_APM_API_KEY)%'
```

### Transaction Configuration

```yaml
elastic_apm:
    # Transaction naming strategy
    transaction_naming_strategy: route  # Options: route, controller, uri, service, message
    
    # Sampling rate (0.0 to 1.0)
    sample_rate: 1.0  # 100% sampling
    
    # Maximum spans per transaction
    max_spans: 1000
```

### Error Tracking

```yaml
elastic_apm:
    # Capture exceptions automatically
    capture_exceptions: true
    
    # Ignored exceptions (won't be reported)
    ignored_exceptions:
        - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
        - Symfony\Component\Security\Core\Exception\AccessDeniedException
```

## Configuration by Environment

### Development Configuration

```yaml
# config/packages/dev/elastic_apm.yaml
elastic_apm:
    enabled: false  # Disable in development
    # OR use blackhole interactor
    interactor: blackhole  # No-op implementation
```

### Production Configuration

```yaml
# config/packages/prod/elastic_apm.yaml
elastic_apm:
    enabled: true
    sample_rate: 0.1  # Sample 10% of requests
    capture_exceptions: true
    environment: production
```

### Test Configuration

```yaml
# config/packages/test/elastic_apm.yaml
elastic_apm:
    enabled: false
    interactor: blackhole
```

## Interactor Selection

Choose your APM backend implementation:

```yaml
elastic_apm:
    # Default: elastic_apm
    interactor: elastic_apm
    
    # Available options:
    # - elastic_apm: Standard Elastic APM
    # - opentracing: For Jaeger/Zipkin
    # - blackhole: No-op for testing
    # - adaptive: Runtime switching
```

## Authentication Methods

### Using Secret Token

```yaml
elastic_apm:
    server_url: 'https://apm.example.com'
    secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
```

### Using API Key

```yaml
elastic_apm:
    server_url: 'https://apm.example.com'
    api_key: '%env(ELASTIC_APM_API_KEY)%'
```

### No Authentication (Development)

```yaml
elastic_apm:
    server_url: 'http://localhost:8200'
    # No secret_token or api_key needed
```

## Performance Tuning

### Sampling Configuration

```yaml
elastic_apm:
    # Dynamic sampling based on transaction name
    transactions:
        sample_rate: 0.1  # Default rate
        sample_rates:
            # Higher sampling for critical endpoints
            'app_checkout_*': 0.5
            'app_payment_*': 1.0
            # Lower sampling for health checks
            'app_health_check': 0.01
```

### Span Limits

```yaml
elastic_apm:
    # Prevent memory issues
    max_spans: 500  # Default: 1000
    
    # Drop spans when limit reached
    span_drop_strategy: 'oldest'  # or 'random'
```

## Message Queue Configuration

```yaml
elastic_apm:
    messenger:
        enabled: true
        # Track message handler execution
        capture_handlers: true
        # Include message data in traces
        capture_message_data: false  # Set to false for PII safety
        # Ignored transports
        ignored_transports: ['sync']
```

## Using Environment Variables

### .env File Example

```bash
###> elastic-apm-symfony-bundle ###
ELASTIC_APM_ENABLED=true
ELASTIC_APM_SERVICE_NAME=my-symfony-app
ELASTIC_APM_SERVICE_VERSION=1.0.0
ELASTIC_APM_SERVER_URL=http://localhost:8200
ELASTIC_APM_SECRET_TOKEN=my-secret-token
ELASTIC_APM_ENVIRONMENT=development
###< elastic-apm-symfony-bundle ###
```

### Docker Compose Example

```yaml
services:
  app:
    environment:
      ELASTIC_APM_ENABLED: "true"
      ELASTIC_APM_SERVICE_NAME: "${APP_NAME:-my-app}"
      ELASTIC_APM_SERVER_URL: "http://apm-server:8200"
      ELASTIC_APM_SECRET_TOKEN: "${APM_SECRET_TOKEN}"
      ELASTIC_APM_ENVIRONMENT: "${APP_ENV:-dev}"
```

## Validation

The bundle validates configuration on boot. Common validation errors:

### Missing Required Fields

```
The child config "service_name" under "elastic_apm" must be configured.
```

**Solution:** Add the missing configuration:
```yaml
elastic_apm:
    service_name: 'my-app'
```

### Invalid Transaction Naming Strategy

```
Invalid configuration for path "elastic_apm.transaction_naming_strategy": 
Invalid naming strategy "custom". Available: route, controller, uri, service, message
```

**Solution:** Use one of the available strategies.

## Configuration Precedence

Configuration is loaded in this order (later overrides earlier):

1. Bundle defaults
2. `config/packages/elastic_apm.yaml`
3. Environment-specific files (e.g., `config/packages/prod/elastic_apm.yaml`)
4. Environment variables
5. Runtime configuration (via services)

## Checking Configuration

### Debug Configuration

```bash
# View processed configuration
php bin/console debug:config elastic_apm

# View raw configuration
php bin/console config:dump elastic_apm
```

### Test Connection

```bash
# Test APM server connection
php bin/console elastic:apm:test-connection
```

## Next Steps

- [Advanced Configuration](advanced.md) - All configuration options
- [Environment Variables](environment-variables.md) - Detailed env var guide
- [Multiple Environments](multiple-environments.md) - Per-environment setup
- [Performance Optimization](../advanced/performance.md) - Tuning for production