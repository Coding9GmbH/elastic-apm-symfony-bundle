# Automatic Instrumentation

The Elastic APM Symfony Bundle automatically instruments many aspects of your application without requiring any code changes. This guide explains what's tracked out of the box.

## HTTP Requests

### What's Tracked

Every HTTP request creates a transaction with:

- **Transaction Name**: Based on your naming strategy (route, controller, URI)
- **Duration**: Total request processing time
- **Result**: HTTP status code (e.g., "HTTP 2xx", "HTTP 4xx")
- **User Context**: If authenticated, user ID and username
- **Request Metadata**:
  - HTTP method (GET, POST, etc.)
  - URL and query parameters
  - User agent
  - Client IP (if not behind proxy)
  - Request headers (sanitized)

### Example Transaction

```
Transaction: GET app_product_show
├── Duration: 245ms
├── Result: HTTP 200
├── User: john.doe (ID: 123)
└── Context:
    ├── request.method: GET
    ├── request.url: /products/42
    └── request.headers.user-agent: Mozilla/5.0...
```

## Database Queries

### Doctrine Integration

All Doctrine queries are automatically tracked as spans:

```
Span: SELECT FROM product
├── Duration: 15ms
├── Type: db.mysql.query
└── Context:
    ├── db.statement: SELECT * FROM product WHERE id = ?
    ├── db.type: mysql
    └── db.instance: main_database
```

### What's Captured

- SQL query (with bound parameters as placeholders)
- Query duration
- Database type and name
- Number of affected rows
- Slow query detection (configurable threshold)

## Console Commands

### Automatic Command Tracking

Every console command execution creates a transaction:

```
Transaction: app:process-orders
├── Duration: 5.2s
├── Type: cli
└── Context:
    ├── command.name: app:process-orders
    ├── command.arguments: --batch-size=100
    └── command.options: {"dry-run": false}
```

### Captured Information

- Command name and description
- Input arguments and options
- Exit code
- Console output (errors only)
- Memory usage

## Exceptions and Errors

### Automatic Exception Capture

All uncaught exceptions are automatically reported:

```
Error: Database connection failed
├── Type: Doctrine\DBAL\Exception\ConnectionException
├── Message: An exception occurred in driver: Connection refused
├── Stack trace: Full stack trace with code context
└── Context:
    ├── Original query: SELECT * FROM users
    └── Connection params: host=localhost, port=3306
```

### What's Included

- Exception class and message
- Full stack trace with file paths and line numbers
- Code snippets around error location
- Request context (if HTTP request)
- User context (if authenticated)
- Custom context data

## Message Queue (Symfony Messenger)

### Automatic Handler Tracking

Every message handler invocation is tracked:

```
Transaction: Consume App\Message\ProcessOrder
├── Duration: 320ms
├── Type: messaging
└── Spans:
    ├── Handle App\Handler\ProcessOrderHandler::__invoke (250ms)
    ├── db.query: INSERT INTO order_history (45ms)
    └── http.request: POST payment-api.com/charge (180ms)
```

### Captured Data

- Message class and handler
- Transport name
- Message properties (if enabled)
- Retry count
- Dispatch metadata

## Cache Operations

### Automatic Cache Tracking

Cache operations are tracked as spans:

```
Span: cache.get
├── Duration: 2ms
├── Type: cache
└── Context:
    ├── cache.key: product_42_details
    ├── cache.pool: cache.app
    └── cache.hit: true
```

### Tracked Operations

- get / set / delete
- clear / prune
- Hit/miss ratio
- Pool name
- Key patterns

## HTTP Client Requests

### Automatic HTTP Client Tracking

Outgoing HTTP requests are tracked:

```
Span: HTTP POST api.example.com
├── Duration: 145ms
├── Type: external.http
└── Context:
    ├── http.method: POST
    ├── http.url: https://api.example.com/webhooks
    ├── http.status_code: 201
    └── http.response_size: 1024
```

## Twig Rendering

### Template Rendering Tracking

Twig template rendering is automatically tracked:

```
Span: Render product/show.html.twig
├── Duration: 35ms
├── Type: template.twig
└── Context:
    ├── template.name: product/show.html.twig
    └── template.cache: false
```

## Event Dispatcher

### Event Tracking

Symfony events are tracked as spans:

```
Span: Event kernel.request
├── Duration: 5ms
├── Type: app.event
└── Context:
    ├── event.name: kernel.request
    └── event.listeners: 12
```

## Security Events

### Authentication Tracking

Security events are automatically captured:

```
Span: Security authentication
├── Duration: 125ms
├── Type: security
└── Context:
    ├── security.authentication: success
    ├── security.user: john.doe
    └── security.provider: app_user_provider
```

## Form Processing

### Form Submission Tracking

Form processing is tracked:

```
Span: Form ProductType
├── Duration: 45ms
├── Type: app.form
└── Context:
    ├── form.name: ProductType
    ├── form.valid: true
    └── form.errors: 0
```

## Memory Usage

### Automatic Memory Tracking

When enabled, memory usage is tracked:

```
Transaction: GET /products
└── Labels:
    ├── memory_usage: 24.5 MB
    ├── memory_peak: 28.3 MB
    └── memory_limit: 256 MB
```

## Custom Automatic Instrumentation

### Service Tags

Tag your services for automatic instrumentation:

```yaml
# config/services.yaml
services:
    App\Service\PaymentService:
        tags:
            - { name: 'elastic_apm.traced_service', span_name: 'payment.process' }
```

### Method Interception

```yaml
# config/packages/elastic_apm.yaml
elastic_apm:
    auto_instrument:
        services:
            - { class: 'App\Service\*', method: 'process*' }
            - { id: 'app.order_service', method: 'createOrder' }
```

## Disabling Automatic Instrumentation

### Disable Specific Features

```yaml
elastic_apm:
    # Disable specific instrumentations
    database:
        enabled: false
    cache:
        enabled: false
    http_client:
        enabled: false
```

### Ignore Patterns

```yaml
elastic_apm:
    transactions:
        ignore_urls:
            - '/health'
            - '/metrics'
            - '*.css'
            - '*.js'
    
    database:
        ignored_statements:
            - 'SELECT 1'
            - 'SHOW VARIABLES'
```

## Performance Considerations

### Overhead

Typical overhead of automatic instrumentation:

| Feature | Overhead |
|---------|----------|
| HTTP Requests | < 1ms |
| Database Queries | < 0.5ms |
| Cache Operations | < 0.1ms |
| Exceptions | < 2ms |

### Reducing Overhead

1. **Use Sampling**:
   ```yaml
   elastic_apm:
       sample_rate: 0.1  # 10% sampling
   ```

2. **Limit Spans**:
   ```yaml
   elastic_apm:
       max_spans: 500
   ```

3. **Disable Unused Features**:
   ```yaml
   elastic_apm:
       cache:
           enabled: false
       twig:
           enabled: false
   ```

## Viewing Instrumentation Data

### In Kibana

1. Navigate to APM
2. Select your service
3. View:
   - Service Map
   - Transactions
   - Errors
   - Metrics

### Example Dashboard View

```
Service: my-symfony-app
├── Transactions (last 24h)
│   ├── GET /products: 145 req/min, 95th: 234ms
│   ├── POST /checkout: 12 req/min, 95th: 890ms
│   └── Command process:orders: 1 req/min, 95th: 5.2s
├── Dependencies
│   ├── mysql: 234 queries/min, avg: 12ms
│   ├── redis: 567 ops/min, avg: 2ms
│   └── payment-api.com: 12 req/min, avg: 145ms
└── Errors (last 24h)
    ├── ConnectionException: 3 occurrences
    └── PaymentFailedException: 1 occurrence
```

## Next Steps

- [Manual Instrumentation](manual-instrumentation.md) - Add custom tracking
- [Message Queue Tracking](message-queue-tracking.md) - Deep dive into Messenger
- [Performance Optimization](../advanced/performance.md) - Reduce overhead
- [Configuration](../configuration/basic.md) - Customize behavior