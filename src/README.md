# ElasticApmBundle

This bundle provides Elastic APM integration for Symfony applications, inspired by the [elastic-apm-symfony-bundle](https://github.com/MySchoolManagement/elastic-apm-symfony-bundle).

## Features

- **Interactor Pattern**: Multiple APM implementations (Elastic APM, OpenTracing, Blackhole, Adaptive)
- **OpenTracing Support**: Full OpenTracing specification compatibility with Jaeger, Zipkin, and OTLP
- **Distributed Tracing**: W3C Trace Context, B3, and Jaeger propagation support
- **Message Queue Tracking**: Comprehensive Symfony Messenger integration with RabbitMQ/AMQP support
- **Transaction Naming Strategies**: Route, Controller, URI, Service, or Message-based naming
- **Event-Driven Integration**: Automatic transaction tracking for HTTP requests, console commands, and message processing
- **Flexible Configuration**: Enable/disable features via configuration
- **RUM Support**: Real User Monitoring configuration endpoint
- **Error Tracking**: Automatic exception and error capturing
- **Memory Tracking**: Optional memory usage tracking

## Configuration

```yaml
elastic_apm:
    enabled: true
    interactor: elastic_apm # Options: elastic_apm, opentracing, blackhole, adaptive
    logging: false
    
    server:
        url: '%env(ELASTIC_APM_SERVER_URL)%'
        secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
        api_key: '%env(ELASTIC_APM_API_KEY)%'
    
    opentracing:
        jaeger_endpoint: '%env(ELASTIC_APM_JAEGER_ENDPOINT)%'  # e.g., http://localhost:14268/api/traces
        zipkin_endpoint: '%env(ELASTIC_APM_ZIPKIN_ENDPOINT)%'  # e.g., http://localhost:9411/api/v2/spans
        format: jaeger # Options: jaeger, zipkin, otlp
        b3_propagation: true      # Enable B3 trace propagation headers
        w3c_propagation: true     # Enable W3C trace context headers
        jaeger_propagation: true  # Enable Jaeger uber-trace-id headers
    
    service:
        name: '%env(ELASTIC_APM_SERVICE_NAME)%'
        version: '%env(ELASTIC_APM_SERVICE_VERSION)%'
        environment: '%env(APP_ENV)%'
    
    transactions:
        sample_rate: 1.0
        max_spans: 1000
        naming_strategy: route # Options: route, controller, uri, service, message
    
    exceptions:
        enabled: true
        ignored_exceptions: []
        unwrap_exceptions: false
    
    memory:
        track_usage: false
        usage_label: memory_usage
    
    messaging:
        enabled: true
        auto_instrument_handlers: false    # Automatically wrap message handlers
        ignored_transports: []             # Transports to ignore
        ignored_message_classes: []        # Message classes to ignore
        track_message_data: true           # Include message data in traces
    
    track_deprecations: false
    track_warnings: false
    
    rum:
        enabled: false                    # Enable frontend monitoring
        expose_config_endpoint: false     # SECURITY: Disable /api/apm/rum-config endpoint
        service_name: 'frontend-app'      # Public service name for frontend
        server_url: null                  # RUM server URL (exposed to frontend)
```

## Usage

### Using the Interactor

```php
use App\Bundle\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

class MyService
{
    public function __construct(
        private ElasticApmInteractorInterface $apmInteractor
    ) {}
    
    public function doSomething(): void
    {
        // Capture a span
        $this->apmInteractor->captureCurrentSpan('database.query', 'db', function() {
            // Your database operation
        }, 'mysql', 'query');
        
        // Add custom context
        $this->apmInteractor->setCustomContext([
            'user_id' => 123,
            'feature' => 'checkout'
        ]);
        
        // Add labels
        $this->apmInteractor->addTransactionLabel('customer_type', 'premium');
    }
}
```

### Using the ApmTracingTrait

```php
use App\Bundle\ElasticApmBundle\Helper\ApmTracingTrait;

class MyService
{
    use ApmTracingTrait;
    
    public function processData(): void
    {
        $this->apmSpan('Process data', 'app.process', function() {
            // Your processing logic
        });
        
        // Database operation
        $this->apmSpan('Query users', 'db', function() {
            // Database query
        }, 'mysql', 'query');
    }
}
```

## Interactors

### ElasticApmInteractor
Full APM integration that sends data to Elastic APM server in the native Elastic format.

### OpenTracingInteractor
OpenTracing-compatible implementation that supports:
- **Jaeger**: Sends traces in Jaeger format to Jaeger collector
- **Zipkin**: Sends traces in Zipkin format to Zipkin collector
- **Distributed Tracing**: Supports W3C Trace Context, B3, and Jaeger propagation
- **Full OpenTracing Semantics**: Tags, logs, baggage, references

### BlackholeInteractor
No-op implementation for testing or when APM is disabled.

### AdaptiveInteractor
Conditionally enables APM based on environment or sampling rate.

## OpenTracing Setup

### Using with Jaeger

1. Set the interactor to `opentracing` in your configuration
2. Configure the Jaeger endpoint:

```yaml
elastic_apm:
    interactor: opentracing
    opentracing:
        jaeger_endpoint: 'http://localhost:14268/api/traces'
        format: jaeger
```

```bash
# Environment variables
ELASTIC_APM_JAEGER_ENDPOINT=http://localhost:14268/api/traces
```

### Using with Zipkin

```yaml
elastic_apm:
    interactor: opentracing
    opentracing:
        zipkin_endpoint: 'http://localhost:9411/api/v2/spans'
        format: zipkin
```

### Distributed Tracing Headers

The OpenTracing interactor automatically handles these trace propagation formats:

- **W3C Trace Context**: `traceparent` and `tracestate` headers
- **B3 Propagation**: `x-b3-traceid`, `x-b3-spanid`, `x-b3-sampled` headers  
- **Jaeger**: `uber-trace-id` header

This ensures seamless trace correlation across microservices regardless of the tracing system used.

## Message Queue / Symfony Messenger Integration

The bundle provides comprehensive tracking for Symfony Messenger and queue processing with automatic transaction creation, distributed tracing, and detailed span tracking.

### Automatic Message Tracking

The `MessengerListener` automatically tracks all message processing:

```yaml
# Enabled by default
elastic_apm:
    messaging:
        enabled: true
```

**What gets tracked automatically:**
- Message consumption from queues (RabbitMQ, Redis, etc.)
- Message sending to transports
- Handler execution time and success/failure
- Message data and metadata
- Transport information
- Retry attempts and failures

### Message-Specific Transaction Names

With the `message` naming strategy, transactions get descriptive names:

```yaml
elastic_apm:
    transactions:
        naming_strategy: message
```

**Example transaction names:**
- `Consume ACCOUNT.CREATE` 
- `Consume CONTACT.UPDATE`
- `Send CUSTOMER_REGISTRATION.SEND_MAIL`
- `Process AccountCommand`

### Manual APM Integration in Message Handlers

Use the `MessageHandlerApmTrait` for fine-grained tracking:

```php
use App\Bundle\ElasticApmBundle\Helper\MessageHandlerApmTrait;

#[AsMessageHandler]
class AccountCommandHandler
{
    use MessageHandlerApmTrait;
    
    public function __invoke(AccountCommand $message): void
    {
        $messageData = $message->getAccountData();
        
        // Add message-specific labels
        $this->apmAddMessageLabels($messageData['properties']);
        
        // Set user context from message
        $this->apmSetUserContextFromMessage($messageData['fields']);
        
        // Track specific operations
        $this->apmTrackMessageOperation('create_account', function() use ($messageData) {
            $this->createAccount($messageData['fields']);
        }, $messageData);
        
        // Track database operations
        $this->apmTrackDatabaseOperation('insert', function() {
            // Database insert
        }, 'Account');
        
        // Track external API calls
        $this->apmTrackExternalCall('crm_service', 'create_account', function() {
            // External API call
        });
    }
}
```

### Distributed Tracing Across Services

Messages automatically propagate trace context:

```php
// In your message publisher
$message = new ExternalOutgoingMessage($action, $type, $data);

// The MessengerListener automatically adds trace headers
// when sending to external systems via RabbitMQ
```

The listener handles trace correlation for:
- **Incoming messages**: Continues traces from external systems
- **Outgoing messages**: Propagates current trace context
- **Internal processing**: Creates child spans for handler operations

### Message Handler Automatic Instrumentation

Enable automatic wrapping of all message handlers:

```yaml
elastic_apm:
    messaging:
        auto_instrument_handlers: true
```

This automatically wraps all `#[AsMessageHandler]` classes with APM tracking without code changes.

### Advanced Messaging Configuration

```yaml
elastic_apm:
    messaging:
        enabled: true
        auto_instrument_handlers: false
        
        # Ignore specific transports
        ignored_transports: 
            - 'sync'
            - 'test'
            
        # Ignore specific message types
        ignored_message_classes:
            - 'App\\Message\\HealthCheck'
            - 'App\\Message\\MetricsCollection'
            
        # Control data inclusion
        track_message_data: true  # Set to false for sensitive data
```

### Message Processing Spans

Each message processing creates detailed spans:

```
Transaction: Consume ACCOUNT.CREATE
├── Handle AccountCommand (message.handler)
├── Validate account data (validation)
├── DB Insert Account (db.mysql.insert)
├── File Upload trade license (storage.s3.upload)
└── External CRM sync (external.http.post)
```

### Error Tracking and Retries

Failed messages are automatically tracked with:
- Exception details and stack traces
- Retry count and will-retry status
- Message data for debugging
- Transport and handler information

### Performance Monitoring

Track message processing performance:
- **Processing time**: End-to-end message handling duration
- **Queue time**: Time spent waiting in queue (when available)
- **Handler breakdown**: Individual operation timings within handlers
- **Throughput**: Messages processed per second
- **Error rates**: Success/failure ratios by message type

## Transaction Naming Strategies

- **RouteNamingStrategy**: Uses Symfony route names (e.g., `GET app_user_list`)
- **ControllerNamingStrategy**: Uses controller class and method (e.g., `GET UserController::list`)
- **UriNamingStrategy**: Uses URI pattern with placeholders (e.g., `GET /users/{id}`)
- **ServiceNamingStrategy**: Custom service-based naming (e.g., `my-app.get.user_list`)
- **MessageNamingStrategy**: Message-based naming for queue operations (e.g., `Consume ACCOUNT.CREATE`)

## Security Considerations

### RUM Configuration Endpoint Security

The bundle includes a RUM configuration endpoint (`/api/apm/rum-config`) that **exposes internal service configuration**. This presents security risks:

**Security Risks:**
- Exposes service names, versions, environments
- Reveals APM server URLs (internal infrastructure)
- Public access to internal configuration details

**Secure Configuration (Recommended):**
```yaml
elastic_apm:
    rum:
        enabled: false                    # Only enable if needed
        expose_config_endpoint: false     # Keep disabled (default)
```

**If You Need RUM:**

**Option 1: Secure Inline Configuration (Recommended)**
```twig
{# In your Twig template #}
{{ apm_rum_script() }}
{# OR #}
<script>{{ apm_rum_config_inline() }}</script>
```

**Option 2: Secured API Endpoint (Advanced)**
```yaml
elastic_apm:
    rum:
        expose_config_endpoint: true      # Only if necessary
```

Then secure the endpoint:
```php
// Uncomment in ApmController.php:
#[IsGranted('ROLE_USER')]  // Require authentication
```

**Additional Security Measures:**
- Implement rate limiting
- Restrict by origin/IP
- Use CORS policies
- Monitor access logs

### Sensitive Data in Traces

**Message Data Tracking:**
```yaml
elastic_apm:
    messaging:
        track_message_data: false  # Disable if messages contain PII/secrets
```

**Custom Context:**
```php
// Be careful with sensitive data
$this->apmInteractor->setCustomContext([
    'user_id' => $userId,           // OK
    'password' => $password,        // NEVER do this
    'api_key' => $apiKey,          // NEVER do this
]);
```