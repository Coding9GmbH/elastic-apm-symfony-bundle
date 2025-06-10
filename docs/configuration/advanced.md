# Advanced Configuration

This guide covers all available configuration options for the Elastic APM Symfony Bundle.

## Complete Configuration Reference

```yaml
elastic_apm:
    # Core Settings
    enabled: true
    interactor: elastic_apm  # elastic_apm, opentracing, blackhole, adaptive
    logging: false           # Enable debug logging
    
    # Server Configuration
    server:
        url: '%env(ELASTIC_APM_SERVER_URL)%'
        secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
        api_key: '%env(ELASTIC_APM_API_KEY)%'
        timeout: 10          # Connection timeout in seconds
        verify_server_cert: true
        proxy:
            url: 'http://proxy.example.com:8080'
            username: '%env(PROXY_USERNAME)%'
            password: '%env(PROXY_PASSWORD)%'
    
    # Service Information
    service:
        name: '%env(ELASTIC_APM_SERVICE_NAME)%'
        version: '%env(ELASTIC_APM_SERVICE_VERSION)%'
        environment: '%env(APP_ENV)%'
        node_name: '%env(HOSTNAME)%'
        framework:
            name: 'Symfony'
            version: '%kernel.symfony_version%'
        runtime:
            name: 'PHP'
            version: '%env(PHP_VERSION)%'
        language:
            name: 'PHP'
            version: '%env(PHP_VERSION)%'
    
    # Transaction Configuration
    transactions:
        enabled: true
        sample_rate: 1.0
        max_spans: 1000
        naming_strategy: route
        custom_naming_service: null  # Service ID for custom strategy
        capture_body: 'off'  # off, errors, transactions, all
        capture_headers: true
        sanitize_field_names:
            - 'password'
            - 'passwd'
            - 'pwd'
            - 'secret'
            - 'token'
            - 'apikey'
            - 'api_key'
            - 'access_token'
            - 'auth'
            - 'credentials'
            - 'mysql_pwd'
            - 'stripetoken'
            - 'card[number]'
            - 'card[cvc]'
            - 'card[cvv]'
            - 'authorization'
            - 'cookie'
            - 'set-cookie'
        transaction_max_duration: '30s'
        ignore_urls:
            - '/health'
            - '/metrics'
            - '/favicon.ico'
            - '*.js'
            - '*.css'
            - '*.jpg'
            - '*.png'
            - '*.gif'
            - '*.webp'
        sample_rates:  # Per-transaction sample rates
            'app_homepage': 0.1
            'app_checkout_*': 1.0
            'app_admin_*': 0.5
    
    # Span Configuration
    spans:
        enabled: true
        stack_trace_limit: 50
        collect_db_queries: true
        db_query_max_length: 10000
        compression:
            enabled: true
            type: gzip
            level: 6
    
    # Exception Configuration
    exceptions:
        enabled: true
        capture_exception_data: true
        ignored_exceptions:
            - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
            - Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
            - Symfony\Component\Security\Core\Exception\AccessDeniedException
            - Symfony\Component\Security\Core\Exception\AuthenticationException
        ignored_exception_messages:
            '/Connection refused/'
            '/Timeout/'
        unwrap_exceptions: false
        capture_error_log_stack_traces: true
    
    # Memory Tracking
    memory:
        enabled: false
        track_usage: true
        usage_label: 'memory_usage'
        track_peak: true
        peak_label: 'memory_peak'
        threshold: 100  # MB
        interval: 1000  # ms
    
    # Messaging Configuration
    messaging:
        enabled: true
        auto_instrument_handlers: true
        capture_message_body: false  # PII consideration
        capture_message_headers: true
        ignored_transports:
            - 'sync'
            - 'in-memory'
        ignored_message_classes:
            - 'App\Message\PingMessage'
            - 'App\Message\HealthCheckMessage'
        transaction_naming:
            pattern: 'Consume {message_class}'
        span_naming:
            pattern: 'Handle {handler_class}::{method}'
        propagate_tracing_headers: true
        header_format: 'w3c'  # w3c, b3, jaeger
    
    # HTTP Client Configuration
    http_client:
        enabled: true
        capture_request_body: false
        capture_response_body: false
        ignored_hosts:
            - 'localhost'
            - '127.0.0.1'
            - 'apm.example.com'
        span_naming:
            pattern: 'HTTP {method} {host}'
    
    # Database Configuration
    database:
        enabled: true
        capture_queries: true
        query_max_length: 10000
        explain_queries: false  # Performance impact!
        slow_query_threshold: 100  # ms
        ignored_statements:
            - 'BEGIN'
            - 'COMMIT'
            - 'ROLLBACK'
    
    # Cache Configuration
    cache:
        enabled: true
        capture_operations: true
        ignored_pools:
            - 'cache.app'
            - 'cache.system'
    
    # RUM (Real User Monitoring) Configuration
    rum:
        enabled: false
        expose_config_endpoint: false  # Security risk!
        allowed_origins:
            - 'https://example.com'
            - 'https://app.example.com'
        server_url: '%env(ELASTIC_APM_RUM_SERVER_URL)%'
        service_name: '%env(ELASTIC_APM_RUM_SERVICE_NAME)%'
        service_version: '%env(ELASTIC_APM_RUM_SERVICE_VERSION)%'
        environment: '%env(APP_ENV)%'
        sample_rate: 0.1
        page_load_trace_id: true
        page_load_sampled: true
        page_load_span_id: true
        propagate_tracing: true
        distributed_tracing_origins:
            - 'https://api.example.com'
    
    # OpenTracing Configuration (when using opentracing interactor)
    opentracing:
        implementation: jaeger  # jaeger, zipkin
        jaeger:
            endpoint: 'http://localhost:14268/api/traces'
            agent_host: 'localhost'
            agent_port: 6831
            sampler:
                type: 'probabilistic'
                param: 0.1
        zipkin:
            endpoint: 'http://localhost:9411/api/v2/spans'
            local_endpoint:
                service_name: '%env(ELASTIC_APM_SERVICE_NAME)%'
                ipv4: '127.0.0.1'
                port: 8080
        propagation:
            formats:
                - 'b3'
                - 'w3c'
                - 'jaeger'
        tags:  # Global tags for all spans
            environment: '%env(APP_ENV)%'
            datacenter: 'us-east-1'
            team: 'backend'
    
    # Distributed Tracing
    distributed_tracing:
        enabled: true
        propagation_format: 'w3c'  # w3c, b3-multi, b3-single, jaeger
        incoming_headers:
            - 'traceparent'
            - 'tracestate'
            - 'b3'
            - 'X-B3-TraceId'
            - 'X-B3-SpanId'
            - 'X-B3-Sampled'
            - 'uber-trace-id'
        outgoing_headers:
            - 'traceparent'
            - 'tracestate'
    
    # Metrics Configuration
    metrics:
        enabled: true
        sets:
            - 'cpu'
            - 'memory'
            - 'breakdown'
        custom_metrics:
            cache_hit_rate:
                type: 'gauge'
                unit: 'percent'
            queue_size:
                type: 'counter'
                unit: 'items'
    
    # Advanced Settings
    advanced:
        async_transport: false
        background_sending: true
        queue_size: 1000
        flush_interval: 10  # seconds
        max_queue_age: 30   # seconds
        shutdown_timeout: 5  # seconds
        gc_collection_interval: 60  # seconds
        breakdown_metrics: true
        central_config: true
        config_polling_interval: 30  # seconds
        cloud_provider: 'auto'  # auto, aws, gcp, azure, none
        global_labels:
            team: 'backend'
            region: 'us-east-1'
            cluster: 'production'
```

## Advanced Interactor Configuration

### Adaptive Interactor

The adaptive interactor allows runtime switching between implementations:

```yaml
elastic_apm:
    interactor: adaptive
    adaptive:
        default: blackhole  # Start with no-op
        switch_on_error: true
        fallback_on_exception: true
        health_check_interval: 60  # seconds
```

```php
// Runtime switching
$adaptiveInteractor = $container->get('elastic_apm.interactor.adaptive');
$adaptiveInteractor->setInteractor($container->get('elastic_apm.interactor.elastic'));
```

### Custom Interactor

Create your own interactor:

```yaml
elastic_apm:
    interactor: custom
    custom_interactor_service: 'app.apm.custom_interactor'
```

```php
namespace App\APM;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

class CustomInteractor implements ElasticApmInteractorInterface
{
    // Implement all interface methods
}
```

## Performance Optimization

### Async Transport

```yaml
elastic_apm:
    advanced:
        async_transport: true
        background_sending: true
        queue_size: 5000
        flush_interval: 5
```

### Sampling Strategies

```yaml
elastic_apm:
    transactions:
        sample_rate: 0.1  # Base rate
        sampling_strategy: 'adaptive'  # adaptive, probabilistic, rate_limit
        adaptive_sampling:
            target_tps: 10  # Target transactions per second
            min_rate: 0.001
            max_rate: 1.0
        rate_limit_sampling:
            limit: 100  # Max transactions per second
```

### Memory Management

```yaml
elastic_apm:
    spans:
        max_spans: 500
        span_drop_strategy: 'priority'  # priority, oldest, random
        priority_patterns:
            - 'db.*'      # High priority
            - 'cache.*'   # Medium priority
            - 'http.*'    # Low priority
    
    advanced:
        max_queue_age: 10
        gc_collection_interval: 30
```

## Conditional Configuration

### Feature Flags

```yaml
elastic_apm:
    features:
        experimental_metrics: '%env(bool:APM_EXPERIMENTAL_METRICS)%'
        breakdown_metrics: true
        continuous_profiling: false
```

### Dynamic Configuration

```php
namespace App\APM;

use ElasticApmBundle\DependencyInjection\Configuration;

class DynamicConfigProvider
{
    public function getConfiguration(): array
    {
        return [
            'enabled' => $this->shouldEnableAPM(),
            'sample_rate' => $this->calculateSampleRate(),
        ];
    }
    
    private function shouldEnableAPM(): bool
    {
        // Custom logic
        return !$this->isMaintenanceMode() && $this->hasValidLicense();
    }
    
    private function calculateSampleRate(): float
    {
        // Adjust based on load
        $currentLoad = $this->getSystemLoad();
        return $currentLoad > 0.8 ? 0.01 : 0.1;
    }
}
```

## Security Configuration

### Sanitization

```yaml
elastic_apm:
    security:
        sanitize_field_names:
            - '/.*password.*/'
            - '/.*token.*/'
            - '/.*secret.*/'
            - '/.*key.*/'
            - '/.*auth.*/'
        redact_headers:
            - 'authorization'
            - 'cookie'
            - 'set-cookie'
            - 'x-api-key'
        mask_errors: true
        error_message_max_length: 1000
```

### Encryption

```yaml
elastic_apm:
    server:
        url: 'https://apm.example.com'
        verify_server_cert: true
        ca_cert_path: '/path/to/ca.crt'
        client_cert_path: '/path/to/client.crt'
        client_key_path: '/path/to/client.key'
```

## Debugging Configuration

### Verbose Logging

```yaml
elastic_apm:
    debug:
        enabled: true
        log_level: 'debug'  # debug, info, warning, error
        log_file: '%kernel.logs_dir%/apm.log'
        log_requests: true
        log_responses: true
        pretty_print: true
```

### Development Tools

```yaml
elastic_apm:
    dev_tools:
        enable_profiler_integration: true
        show_spans_in_toolbar: true
        collect_render_time: true
        trace_twig_rendering: true
```

## Next Steps

- [Environment Variables](environment-variables.md) - External configuration
- [Multiple Environments](multiple-environments.md) - Per-environment setup
- [Performance Tuning](../advanced/performance.md) - Optimization guide
- [Custom Interactors](../advanced/custom-interactors.md) - Build your own