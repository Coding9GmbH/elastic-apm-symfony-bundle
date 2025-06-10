# Installation

## Prerequisites

Before installing the Elastic APM Symfony Bundle, ensure your system meets the following requirements:

- PHP 7.4 or higher
- Symfony 5.4, 6.x, or 7.x
- Composer 2.x
- One of the following APM backends (optional):
  - Elastic APM Server 7.x or 8.x
  - Jaeger 1.x
  - Zipkin 2.x

## Installation via Composer

### Step 1: Install the Bundle

```bash
composer require yourvendor/elastic-apm-symfony-bundle
```

### Step 2: Bundle Registration

If you're using Symfony Flex (recommended), the bundle will be automatically registered. 

For manual registration, add the bundle to `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    ElasticApmBundle\ElasticApmBundle::class => ['all' => true],
];
```

### Step 3: Install the APM Agent

The bundle requires the Elastic APM PHP agent:

```bash
composer require nipwaayoni/elastic-apm-php-agent
```

For OpenTracing support (Jaeger/Zipkin), also install:

```bash
composer require opentracing/opentracing jaegertracing/jaeger-client-php
```

## Basic Configuration

### Step 1: Create Configuration File

Create `config/packages/elastic_apm.yaml`:

```yaml
elastic_apm:
    enabled: '%env(bool:ELASTIC_APM_ENABLED)%'
    service_name: '%env(ELASTIC_APM_SERVICE_NAME)%'
    server_url: '%env(ELASTIC_APM_SERVER_URL)%'
    secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
```

### Step 2: Set Environment Variables

Add to your `.env` file:

```bash
###> elastic-apm-symfony-bundle ###
ELASTIC_APM_ENABLED=true
ELASTIC_APM_SERVICE_NAME=my-symfony-app
ELASTIC_APM_SERVER_URL=http://localhost:8200
ELASTIC_APM_SECRET_TOKEN=your-secret-token
###< elastic-apm-symfony-bundle ###
```

### Step 3: Clear Cache

```bash
php bin/console cache:clear
```

## Verification

To verify the installation:

1. **Check Bundle Registration:**
   ```bash
   php bin/console debug:container --tag=elastic_apm
   ```

2. **Test Connection:**
   ```bash
   php bin/console elastic:apm:test
   ```

3. **Check Logs:**
   ```bash
   tail -f var/log/dev.log | grep -i apm
   ```

## Docker Installation

For Docker environments, use environment variables in your `docker-compose.yml`:

```yaml
services:
  app:
    environment:
      ELASTIC_APM_ENABLED: "true"
      ELASTIC_APM_SERVICE_NAME: "my-app"
      ELASTIC_APM_SERVER_URL: "http://apm-server:8200"
      ELASTIC_APM_SECRET_TOKEN: "${APM_SECRET_TOKEN}"
```

## Production Installation

For production environments:

1. **Use Production Values:**
   ```bash
   # .env.prod
   ELASTIC_APM_ENABLED=true
   ELASTIC_APM_SERVICE_NAME=production-app
   ELASTIC_APM_SERVER_URL=https://apm.example.com
   ELASTIC_APM_SECRET_TOKEN="${SECRET_APM_TOKEN}"
   ELASTIC_APM_ENVIRONMENT=production
   ```

2. **Optimize Autoloader:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Warm Up Cache:**
   ```bash
   php bin/console cache:warmup --env=prod
   ```

## Troubleshooting Installation

### Bundle Not Found

If you get "Class 'ElasticApmBundle\ElasticApmBundle' not found":

```bash
composer dump-autoload
```

### Permission Issues

Ensure proper permissions:

```bash
chmod -R 777 var/cache var/log
```

### Connection Failed

Check APM server connectivity:

```bash
curl -X GET "http://localhost:8200/"
```

## Next Steps

- [Quick Start Guide](quick-start.md) - Get started with basic usage
- [Configuration Reference](../configuration/basic.md) - Explore configuration options
- [Usage Examples](../usage/automatic-instrumentation.md) - Learn what's tracked automatically