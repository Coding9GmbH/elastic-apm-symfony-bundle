# ElasticApmInteractorInterface API Reference

The `ElasticApmInteractorInterface` is the main interface for interacting with APM functionality. All interactor implementations (Elastic APM, OpenTracing, Blackhole, Adaptive) implement this interface.

## Interface Definition

```php
namespace ElasticApmBundle\Interactor;

use Nipwaayoni\Events\Transaction;
use Nipwaayoni\Events\Span;

interface ElasticApmInteractorInterface
{
    public function startTransaction(string $name, string $type): Transaction;
    
    public function stopTransaction(?Transaction $transaction, ?int $result = null): void;
    
    public function startSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?Transaction $transaction = null
    ): Span;
    
    public function stopSpan(Span $span): void;
    
    public function captureException(\Throwable $exception): void;
    
    public function captureError(string $message, array $context = []): void;
    
    public function setTransactionCustomData(Transaction $transaction, array $data): void;
    
    public function setSpanCustomData(Span $span, array $data): void;
    
    public function setUserContext(array $context): void;
    
    public function setCustomContext(array $context): void;
    
    public function setLabels(array $labels): void;
    
    public function flush(): void;
    
    public function isEnabled(): bool;
    
    public function isRecording(): bool;
    
    public function getCurrentTransaction(): ?Transaction;
    
    public function getTraceContext(): array;
    
    public function captureCurrentSpan(
        string $name,
        string $type,
        callable $callback,
        array $context = []
    ): mixed;
}
```

## Method Reference

### Transaction Management

#### startTransaction()

Starts a new APM transaction.

```php
public function startTransaction(string $name, string $type): Transaction
```

**Parameters:**
- `$name` - Transaction name (e.g., "GET /products", "ProcessOrder")
- `$type` - Transaction type (e.g., "request", "cli", "messaging")

**Returns:** Transaction object

**Example:**
```php
$transaction = $apm->startTransaction('Import products', 'cli');
```

#### stopTransaction()

Stops a transaction and optionally sets its result.

```php
public function stopTransaction(?Transaction $transaction, ?int $result = null): void
```

**Parameters:**
- `$transaction` - Transaction to stop (null for current)
- `$result` - HTTP status code or custom result code

**Example:**
```php
$apm->stopTransaction($transaction, 200);
```

#### getCurrentTransaction()

Gets the currently active transaction.

```php
public function getCurrentTransaction(): ?Transaction
```

**Returns:** Current transaction or null

**Example:**
```php
$currentTransaction = $apm->getCurrentTransaction();
if ($currentTransaction) {
    // Add custom data to current transaction
}
```

### Span Management

#### startSpan()

Starts a new span within a transaction.

```php
public function startSpan(
    string $name,
    string $type,
    ?string $subtype = null,
    ?Transaction $transaction = null
): Span
```

**Parameters:**
- `$name` - Span name (e.g., "SELECT FROM users")
- `$type` - Span type (e.g., "db", "cache", "external")
- `$subtype` - Optional subtype (e.g., "mysql", "redis", "http")
- `$transaction` - Parent transaction (null for current)

**Returns:** Span object

**Example:**
```php
$span = $apm->startSpan('Query users', 'db', 'mysql');
```

#### stopSpan()

Stops a span.

```php
public function stopSpan(Span $span): void
```

**Parameters:**
- `$span` - Span to stop

**Example:**
```php
$apm->stopSpan($span);
```

#### captureCurrentSpan()

Executes a callback within a span (convenience method).

```php
public function captureCurrentSpan(
    string $name,
    string $type,
    callable $callback,
    array $context = []
): mixed
```

**Parameters:**
- `$name` - Span name
- `$type` - Span type
- `$callback` - Function to execute
- `$context` - Optional context data

**Returns:** Result of callback

**Example:**
```php
$result = $apm->captureCurrentSpan('Process data', 'business', function() {
    return $this->processData();
}, ['batch_size' => 100]);
```

### Error and Exception Handling

#### captureException()

Captures an exception with full stack trace.

```php
public function captureException(\Throwable $exception): void
```

**Parameters:**
- `$exception` - Exception to capture

**Example:**
```php
try {
    $this->riskyOperation();
} catch (\Exception $e) {
    $apm->captureException($e);
    throw $e;
}
```

#### captureError()

Captures a custom error message.

```php
public function captureError(string $message, array $context = []): void
```

**Parameters:**
- `$message` - Error message
- `$context` - Additional context

**Example:**
```php
$apm->captureError('Payment validation failed', [
    'payment_id' => $paymentId,
    'errors' => $validationErrors
]);
```

### Context Management

#### setUserContext()

Sets user context for the current transaction.

```php
public function setUserContext(array $context): void
```

**Parameters:**
- `$context` - User data (id, username, email, etc.)

**Example:**
```php
$apm->setUserContext([
    'id' => $user->getId(),
    'username' => $user->getUsername(),
    'subscription' => $user->getSubscriptionType()
]);
```

#### setCustomContext()

Sets custom context data.

```php
public function setCustomContext(array $context): void
```

**Parameters:**
- `$context` - Custom key-value pairs

**Example:**
```php
$apm->setCustomContext([
    'order' => [
        'id' => $order->getId(),
        'total' => $order->getTotal(),
        'items' => $order->getItemCount()
    ],
    'shipping' => [
        'method' => 'express',
        'country' => 'US'
    ]
]);
```

#### setLabels()

Sets searchable labels.

```php
public function setLabels(array $labels): void
```

**Parameters:**
- `$labels` - Label key-value pairs

**Example:**
```php
$apm->setLabels([
    'customer_tier' => 'premium',
    'feature_flag' => 'new_checkout',
    'datacenter' => 'us-east-1'
]);
```

### Transaction and Span Data

#### setTransactionCustomData()

Adds custom data to a transaction.

```php
public function setTransactionCustomData(Transaction $transaction, array $data): void
```

**Parameters:**
- `$transaction` - Target transaction
- `$data` - Custom data

**Example:**
```php
$apm->setTransactionCustomData($transaction, [
    'api_version' => '2.0',
    'client_id' => $clientId
]);
```

#### setSpanCustomData()

Adds custom data to a span.

```php
public function setSpanCustomData(Span $span, array $data): void
```

**Parameters:**
- `$span` - Target span
- `$data` - Custom data

**Example:**
```php
$apm->setSpanCustomData($span, [
    'cache_hit' => true,
    'cache_key' => $key,
    'ttl' => 3600
]);
```

### Distributed Tracing

#### getTraceContext()

Gets trace context for distributed tracing.

```php
public function getTraceContext(): array
```

**Returns:** Array with trace headers

**Example:**
```php
$traceContext = $apm->getTraceContext();
// Returns:
// [
//     'traceparent' => '00-trace-id-span-id-01',
//     'tracestate' => 'vendor=data'
// ]

// Use in HTTP request
$httpClient->request('GET', '/api', [
    'headers' => $traceContext
]);
```

### Utility Methods

#### flush()

Sends all queued APM data immediately.

```php
public function flush(): void
```

**Example:**
```php
// Ensure data is sent before script ends
$apm->flush();
```

#### isEnabled()

Checks if APM is enabled.

```php
public function isEnabled(): bool
```

**Returns:** True if APM is enabled

**Example:**
```php
if ($apm->isEnabled()) {
    // Perform APM operations
}
```

#### isRecording()

Checks if the current transaction is being recorded (sampling).

```php
public function isRecording(): bool
```

**Returns:** True if recording

**Example:**
```php
if ($apm->isRecording()) {
    // Add expensive instrumentation
    $apm->captureCurrentSpan('expensive_operation', 'app', function() {
        // ...
    });
}
```

## Usage Examples

### Complete Transaction Example

```php
class OrderProcessor
{
    public function __construct(
        private ElasticApmInteractorInterface $apm
    ) {}
    
    public function processOrder(Order $order): void
    {
        $transaction = $this->apm->startTransaction(
            'Process Order #' . $order->getId(),
            'business'
        );
        
        try {
            // Set user context
            $this->apm->setUserContext([
                'id' => $order->getUserId(),
                'type' => 'customer'
            ]);
            
            // Set transaction labels
            $this->apm->setLabels([
                'order_type' => $order->getType(),
                'priority' => $order->getPriority()
            ]);
            
            // Validate order
            $validationSpan = $this->apm->startSpan('Validate order', 'app');
            $this->validateOrder($order);
            $this->apm->stopSpan($validationSpan);
            
            // Process payment
            $paymentSpan = $this->apm->startSpan('Process payment', 'external', 'payment_api');
            try {
                $paymentResult = $this->paymentService->charge($order);
                $this->apm->setSpanCustomData($paymentSpan, [
                    'payment_id' => $paymentResult->getId(),
                    'gateway' => $paymentResult->getGateway()
                ]);
            } catch (\Exception $e) {
                $this->apm->captureException($e);
                throw $e;
            } finally {
                $this->apm->stopSpan($paymentSpan);
            }
            
            // Update inventory
            $this->apm->captureCurrentSpan('Update inventory', 'db', function() use ($order) {
                $this->inventoryService->reserve($order->getItems());
            });
            
            // Set success result
            $transaction->setResult('success');
            
        } catch (\Exception $e) {
            $transaction->setResult('error');
            throw $e;
        } finally {
            $this->apm->stopTransaction($transaction);
            $this->apm->flush();
        }
    }
}
```

### Error Handling Example

```php
class DataImporter
{
    public function import(string $file): void
    {
        try {
            $data = $this->parseFile($file);
        } catch (ParseException $e) {
            // Capture but continue with fallback
            $this->apm->captureException($e);
            $this->apm->captureError('File parse failed, using defaults', [
                'file' => $file,
                'error' => $e->getMessage()
            ]);
            $data = $this->getDefaultData();
        }
        
        $this->processData($data);
    }
}
```

## Best Practices

1. **Always stop spans and transactions** in finally blocks
2. **Use meaningful names** that describe the operation
3. **Include relevant context** but avoid sensitive data
4. **Check isRecording()** before expensive operations
5. **Flush data** for critical transactions
6. **Handle exceptions** appropriately

## Next Steps

- [Helper Traits](helper-traits.md) - Convenient helper methods
- [Naming Strategies](naming-strategies.md) - Transaction naming
- [Manual Instrumentation](../usage/manual-instrumentation.md) - Usage guide
- [Configuration](../configuration/advanced.md) - Configuration options