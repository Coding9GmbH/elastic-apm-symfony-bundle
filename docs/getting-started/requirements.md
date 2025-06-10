# Requirements

## System Requirements

### PHP Version

| PHP Version | Support Status |
|-------------|----------------|
| 7.4         | ✅ Supported   |
| 8.0         | ✅ Supported   |
| 8.1         | ✅ Supported   |
| 8.2         | ✅ Supported   |
| 8.3         | ✅ Supported   |

### Symfony Version

| Symfony Version | Support Status |
|-----------------|----------------|
| 5.4 LTS         | ✅ Supported   |
| 6.0             | ✅ Supported   |
| 6.1             | ✅ Supported   |
| 6.2             | ✅ Supported   |
| 6.3             | ✅ Supported   |
| 6.4 LTS         | ✅ Supported   |
| 7.0             | ✅ Supported   |

### PHP Extensions

Required extensions:
- `ext-json` - JSON encoding/decoding
- `ext-curl` - HTTP communication with APM server

Optional extensions:
- `ext-mbstring` - Better string handling
- `ext-pcntl` - Process control for async operations

## APM Server Requirements

### Elastic APM Server

- Version 7.0 or higher
- Version 8.0+ recommended for latest features
- HTTPS recommended for production

### Alternative APM Backends

**Jaeger:**
- Version 1.20 or higher
- Requires `jaegertracing/jaeger-client-php`

**Zipkin:**
- Version 2.21 or higher
- HTTP API v2 support

## Dependency Requirements

### Required Dependencies

```json
{
    "nipwaayoni/elastic-apm-php-agent": "^7.0|^8.0",
    "psr/log": "^1.0|^2.0|^3.0"
}
```

### Optional Dependencies

```json
{
    "opentracing/opentracing": "^1.0",
    "jaegertracing/jaeger-client-php": "^1.0",
    "symfony/messenger": "^5.4|^6.0|^7.0",
    "symfony/twig-bundle": "^5.4|^6.0|^7.0",
    "symfony/security-bundle": "^5.4|^6.0|^7.0"
}
```

## Infrastructure Requirements

### Memory

- Minimum: 128MB for APM operations
- Recommended: 256MB+ for high-traffic applications
- Adjust `memory_limit` in `php.ini` if needed

### Network

- Outbound HTTPS connectivity to APM server
- Low latency connection recommended (<50ms)
- Firewall rules allowing APM server communication

### Storage

- Minimal disk space for APM cache
- Log rotation recommended for APM logs

## Development Requirements

For development and testing:

```json
{
    "phpunit/phpunit": "^9.5|^10.0",
    "symfony/test-pack": "^1.0",
    "friendsofphp/php-cs-fixer": "^3.0",
    "phpstan/phpstan": "^1.0"
}
```

## Compatibility Matrix

### PHP and Symfony Compatibility

| PHP Version | Symfony 5.4 | Symfony 6.x | Symfony 7.0 |
|-------------|-------------|-------------|-------------|
| 7.4         | ✅          | ❌          | ❌          |
| 8.0         | ✅          | ✅          | ❌          |
| 8.1         | ✅          | ✅          | ❌          |
| 8.2         | ✅          | ✅          | ✅          |
| 8.3         | ✅          | ✅          | ✅          |

### Feature Availability

| Feature                | Requirement                        |
|------------------------|-----------------------------------|
| Basic APM              | None                              |
| Message Queue Tracking | `symfony/messenger`               |
| RUM Support           | `symfony/twig-bundle`             |
| OpenTracing           | `opentracing/opentracing`        |
| Jaeger Support        | `jaegertracing/jaeger-client-php` |

## Performance Considerations

### Recommended Settings

```ini
; php.ini
memory_limit = 256M
max_execution_time = 300
curl.timeout = 10
```

### APM Server Sizing

| Application Size | Requests/min | APM Server Requirements |
|------------------|--------------|-------------------------|
| Small            | < 1,000      | 2 CPU, 4GB RAM         |
| Medium           | 1,000-10,000 | 4 CPU, 8GB RAM         |
| Large            | > 10,000     | 8+ CPU, 16GB+ RAM      |

## Checking Requirements

Use our requirement checker:

```bash
php bin/console elastic:apm:check-requirements
```

This command verifies:
- PHP version and extensions
- Symfony compatibility
- APM server connectivity
- Required dependencies

## Docker Requirements

For Docker deployments:

```dockerfile
FROM php:8.2-fpm

# Install required extensions
RUN docker-php-ext-install json \
    && pecl install apcu \
    && docker-php-ext-enable apcu

# Install additional tools
RUN apt-get update && apt-get install -y \
    curl \
    && rm -rf /var/lib/apt/lists/*
```

## Cloud Platform Requirements

### AWS

- Security group allowing outbound HTTPS
- IAM role with appropriate permissions (if using AWS-hosted APM)

### Google Cloud

- Firewall rules for APM server access
- Service account with monitoring permissions

### Kubernetes

- NetworkPolicy allowing APM server communication
- Appropriate resource limits and requests

## Next Steps

- [Installation Guide](installation.md) - Install the bundle
- [Configuration](../configuration/basic.md) - Configure for your environment
- [Troubleshooting](../advanced/troubleshooting.md) - Common issues and solutions