# Installation Guide

## Requirements

- PHP 8.1 or higher
- Symfony 6.0 or higher
- ext-curl (for sending APM data)

## Installation Methods

### Method 1: Using Composer (Recommended)

```bash
composer require yourvendor/elastic-apm-symfony-bundle
```

### Method 2: From Source

1. Clone the repository:
```bash
git clone https://github.com/yourvendor/elastic-apm-symfony-bundle.git
```

2. Add to your project's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./path/to/elastic-apm-symfony-bundle"
        }
    ]
}
```

3. Install:
```bash
composer require yourvendor/elastic-apm-symfony-bundle
```

## Manual Setup (if not using Symfony Flex)

### 1. Register the Bundle

Add to `config/bundles.php`:

```php
return [
    // ...
    ElasticApmBundle\ElasticApmBundle::class => ['all' => true],
];
```

### 2. Create Configuration

Create `config/packages/elastic_apm.yaml`:

```yaml
elastic_apm:
    enabled: '%env(bool:ELASTIC_APM_ENABLED)%'
    server:
        url: '%env(ELASTIC_APM_SERVER_URL)%'
        secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
    service:
        name: '%env(ELASTIC_APM_SERVICE_NAME)%'
        version: '%env(ELASTIC_APM_SERVICE_VERSION)%'
```

### 3. Configure Environment Variables

Add to `.env`:

```bash
###> elastic-apm-symfony-bundle ###
ELASTIC_APM_ENABLED=true
ELASTIC_APM_SERVER_URL=http://localhost:8200
ELASTIC_APM_SECRET_TOKEN=
ELASTIC_APM_SERVICE_NAME=my-app
ELASTIC_APM_SERVICE_VERSION=1.0.0
ELASTIC_APM_LOGGING=false
ELASTIC_APM_TRANSACTION_SAMPLE_RATE=1.0
ELASTIC_APM_TRANSACTION_MAX_SPANS=1000
ELASTIC_APM_TRACK_MEMORY=false
ELASTIC_APM_RUM_ENABLED=false
ELASTIC_APM_RUM_SERVICE_NAME=my-app-frontend
ELASTIC_APM_RUM_SERVER_URL=
###< elastic-apm-symfony-bundle ###
```

## Migrating from App\Bundle\ElasticApmBundle

If you're migrating from the embedded bundle:

### 1. Update Namespaces

Replace in your code:
- `App\Bundle\ElasticApmBundle` → `ElasticApmBundle`

### 2. Update Service References

In `services.yaml`:
```yaml
# Before
$apmService: '@App\Service\Apm\ElasticApmService'

# After
$apmInteractor: '@elastic_apm.interactor'
```

### 3. Remove Old Bundle

1. Remove from `config/bundles.php`:
```php
// Remove this line
App\Bundle\ElasticApmBundle\ElasticApmBundle::class => ['all' => true],
```

2. Delete the old bundle directory:
```bash
rm -rf src/Bundle/ElasticApmBundle
```

### 4. Update Imports

```php
// Before
use App\Bundle\ElasticApmBundle\Helper\MessageHandlerApmTrait;

// After
use ElasticApmBundle\Helper\MessageHandlerApmTrait;
```

## Verification

Run these commands to verify installation:

```bash
# Clear cache
php bin/console cache:clear

# Check bundle is loaded
php bin/console debug:config elastic_apm

# Test configuration
php bin/console debug:container elastic_apm.interactor
```

## Troubleshooting

### "Class not found" errors

Clear the autoloader cache:
```bash
composer dump-autoload
```

### Configuration not loading

Check bundle registration:
```bash
php bin/console debug:container --parameter=kernel.bundles
```

### APM data not sending

1. Check your APM server is running
2. Verify credentials in `.env`
3. Enable logging:
```yaml
elastic_apm:
    logging: true
```

## Next Steps

- Configure [transaction naming strategies](README.md#transaction-naming-strategies)
- Set up [message queue tracking](README.md#message-queue--symfony-messenger-integration)
- Enable [RUM for frontend monitoring](README.md#security-considerations)
- Configure [OpenTracing for Jaeger/Zipkin](README.md#opentracing-support-jaegerzipkin)