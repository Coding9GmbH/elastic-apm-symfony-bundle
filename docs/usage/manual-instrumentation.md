# Manual Instrumentation

While automatic instrumentation covers many use cases, manual instrumentation allows you to add custom tracking for business-specific operations, external services, and performance-critical code paths.

## Basic Manual Instrumentation

### Using the Interactor Service

```php
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

class OrderService
{
    public function __construct(
        private ElasticApmInteractorInterface $apm
    ) {}
    
    public function processOrder(Order $order): void
    {
        // Start a custom span
        $span = $this->apm->startSpan('Process order', 'business');
        
        try {
            $this->validateOrder($order);
            $this->calculatePricing($order);
            $this->applyDiscounts($order);
            $this->saveOrder($order);
            
            // Add custom context
            $this->apm->setSpanCustomData($span, [
                'order_id' => $order->getId(),
                'total_amount' => $order->getTotalAmount(),
                'item_count' => $order->getItemCount(),
            ]);
        } finally {
            // Always stop the span
            $this->apm->stopSpan($span);
        }
    }
}
```

### Using Closure-Based Tracking

```php
public function importProducts(string $filename): int
{
    return $this->apm->captureCurrentSpan(
        'Import products',
        'business',
        function() use ($filename) {
            $products = $this->parseFile($filename);
            $this->validateProducts($products);
            return $this->saveProducts($products);
        },
        ['filename' => $filename]  // Optional context
    );
}
```

## Using Helper Traits

### ApmTracingTrait

```php
use ElasticApmBundle\Helper\ApmTracingTrait;

class ProductRepository
{
    use ApmTracingTrait;
    
    public function findByCategory(string $category): array
    {
        return $this->apmTrace('db.query', 'elasticsearch', function() use ($category) {
            return $this->elasticsearch->search([
                'index' => 'products',
                'body' => [
                    'query' => [
                        'match' => ['category' => $category]
                    ]
                ]
            ]);
        }, [
            'index' => 'products',
            'category' => $category
        ]);
    }
    
    public function updateInventory(int $productId, int $quantity): void
    {
        $this->apmTraceVoid('inventory.update', 'business', function() use ($productId, $quantity) {
            $this->connection->executeUpdate(
                'UPDATE inventory SET quantity = ? WHERE product_id = ?',
                [$quantity, $productId]
            );
        });
    }
}
```

### MessageHandlerApmTrait

```php
use ElasticApmBundle\Helper\MessageHandlerApmTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessOrderHandler
{
    use MessageHandlerApmTrait;
    
    public function __invoke(ProcessOrder $message): void
    {
        $orderId = $message->getOrderId();
        
        // Track database operations
        $order = $this->apmTrackDatabaseOperation('select', function() use ($orderId) {
            return $this->orderRepository->find($orderId);
        }, 'Order');
        
        // Track HTTP requests
        $paymentResult = $this->apmTrackHttpRequest('POST', 'payment-api.com', function() use ($order) {
            return $this->paymentClient->charge($order);
        });
        
        // Track cache operations
        $this->apmTrackCacheOperation('set', function() use ($order) {
            $this->cache->set('order_' . $order->getId(), $order, 3600);
        }, 'order_' . $order->getId());
        
        // Track custom business logic
        $this->apmTrackCustomOperation('send_confirmation', function() use ($order) {
            $this->emailService->sendOrderConfirmation($order);
        });
    }
}
```

## Advanced Span Management

### Nested Spans

```php
public function processLargeDataset(array $data): void
{
    $parentSpan = $this->apm->startSpan('Process dataset', 'business');
    
    try {
        // Validation span
        $validationSpan = $this->apm->startSpan('Validate data', 'validation', null, $parentSpan);
        $this->validateData($data);
        $this->apm->stopSpan($validationSpan);
        
        // Processing spans
        foreach (array_chunk($data, 100) as $index => $chunk) {
            $chunkSpan = $this->apm->startSpan(
                sprintf('Process chunk %d', $index),
                'processing',
                null,
                $parentSpan
            );
            
            $this->processChunk($chunk);
            
            $this->apm->setSpanCustomData($chunkSpan, [
                'chunk_size' => count($chunk),
                'chunk_index' => $index
            ]);
            
            $this->apm->stopSpan($chunkSpan);
        }
    } finally {
        $this->apm->stopSpan($parentSpan);
    }
}
```

### Distributed Tracing

```php
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ElasticApmInteractorInterface $apm
    ) {}
    
    public function fetchUserData(int $userId): array
    {
        $span = $this->apm->startSpan('Fetch user data', 'external.http');
        
        try {
            // Get current trace context
            $traceContext = $this->apm->getTraceContext();
            
            // Make request with trace headers
            $response = $this->httpClient->request('GET', "/users/{$userId}", [
                'headers' => [
                    'traceparent' => $traceContext['traceparent'],
                    'tracestate' => $traceContext['tracestate'] ?? '',
                ]
            ]);
            
            $data = $response->toArray();
            
            // Add response metadata
            $this->apm->setSpanCustomData($span, [
                'http.status_code' => $response->getStatusCode(),
                'http.response_size' => strlen($response->getContent()),
                'user_id' => $userId
            ]);
            
            return $data;
        } catch (\Exception $e) {
            $this->apm->captureException($e);
            throw $e;
        } finally {
            $this->apm->stopSpan($span);
        }
    }
}
```

## Custom Transaction Management

### Creating Custom Transactions

```php
class BatchProcessor
{
    public function processBatch(string $batchId): void
    {
        // Start a custom transaction
        $transaction = $this->apm->startTransaction(
            "Process batch {$batchId}",
            'batch_processing'
        );
        
        try {
            // Set transaction metadata
            $this->apm->setTransactionCustomData($transaction, [
                'batch_id' => $batchId,
                'batch_size' => $this->getBatchSize($batchId),
                'processor_version' => '2.0'
            ]);
            
            // Process the batch
            $this->loadBatch($batchId);
            $this->validateBatch();
            $this->processBatchItems();
            $this->saveBatchResults();
            
            // Set success result
            $transaction->setResult('success');
        } catch (\Exception $e) {
            // Set error result
            $transaction->setResult('error');
            $this->apm->captureException($e);
            throw $e;
        } finally {
            // Always stop the transaction
            $this->apm->stopTransaction($transaction);
            $this->apm->flush();  // Ensure data is sent
        }
    }
}
```

## Context and Metadata

### Setting User Context

```php
public function authenticateUser(string $username, string $password): void
{
    $user = $this->authenticate($username, $password);
    
    // Set user context for all subsequent operations
    $this->apm->setUserContext([
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        'email' => $user->getEmail(),
    ]);
}
```

### Setting Custom Context

```php
public function processPayment(Payment $payment): void
{
    // Add payment context
    $this->apm->setCustomContext([
        'payment' => [
            'id' => $payment->getId(),
            'method' => $payment->getMethod(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
        ],
        'merchant' => [
            'id' => $payment->getMerchantId(),
            'category' => $payment->getMerchantCategory(),
        ]
    ]);
    
    // Process payment...
}
```

### Adding Labels

```php
public function importData(string $source): void
{
    // Add searchable labels
    $this->apm->setLabels([
        'import_source' => $source,
        'import_type' => 'full',
        'data_version' => 'v2',
    ]);
    
    // Import logic...
}
```

## Error Handling and Reporting

### Manual Exception Capture

```php
public function processRiskyOperation(): void
{
    try {
        $this->riskyOperation();
    } catch (RecoverableException $e) {
        // Report error but continue
        $this->apm->captureException($e);
        
        // Add error context
        $this->apm->setCustomContext([
            'error_recovery' => [
                'strategy' => 'fallback',
                'attempts' => 3,
            ]
        ]);
        
        // Try fallback
        $this->fallbackOperation();
    }
}
```

### Custom Error Creation

```php
public function validateData(array $data): void
{
    $errors = $this->validator->validate($data);
    
    if (count($errors) > 0) {
        // Create custom error
        $this->apm->captureError('Validation failed', [
            'validation_errors' => $errors,
            'data_sample' => array_slice($data, 0, 5),
        ]);
    }
}
```

## Performance Patterns

### Sampling Heavy Operations

```php
public function processHighVolumeEndpoint(): void
{
    // Only trace 10% of requests
    if ($this->apm->isRecording() && random_int(1, 10) <= 1) {
        $this->apm->captureCurrentSpan('Heavy processing', 'business', function() {
            $this->doHeavyProcessing();
        });
    } else {
        // Execute without tracing
        $this->doHeavyProcessing();
    }
}
```

### Conditional Instrumentation

```php
public function executeQuery(string $sql, array $params = []): mixed
{
    // Only instrument slow queries
    $start = microtime(true);
    $result = $this->connection->executeQuery($sql, $params);
    $duration = (microtime(true) - $start) * 1000; // ms
    
    if ($duration > 100) { // Only track queries over 100ms
        $span = $this->apm->startSpan('Slow query', 'db');
        $this->apm->setSpanCustomData($span, [
            'db.statement' => $sql,
            'db.duration' => $duration,
        ]);
        $this->apm->stopSpan($span);
    }
    
    return $result;
}
```

## Testing with Manual Instrumentation

### Mocking APM in Tests

```php
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    public function testProcessOrder(): void
    {
        $apmMock = $this->createMock(ElasticApmInteractorInterface::class);
        
        // Expect span creation
        $apmMock->expects($this->once())
            ->method('startSpan')
            ->with('Process order', 'business')
            ->willReturn($this->createMock(Span::class));
        
        // Expect span to be stopped
        $apmMock->expects($this->once())
            ->method('stopSpan');
        
        $service = new OrderService($apmMock);
        $service->processOrder(new Order());
    }
}
```

### Using Blackhole Interactor

```yaml
# config/packages/test/elastic_apm.yaml
elastic_apm:
    interactor: blackhole  # No-op implementation for tests
```

## Best Practices

1. **Always Stop Spans**: Use try/finally blocks
2. **Meaningful Names**: Use descriptive span names
3. **Appropriate Types**: Use standard types (db, external, cache)
4. **Avoid Over-Instrumentation**: Focus on important operations
5. **Add Context**: Include relevant debugging information
6. **Handle Errors**: Capture exceptions with context
7. **Test Instrumentation**: Verify APM calls in tests

## Next Steps

- [Message Queue Tracking](message-queue-tracking.md) - Messenger integration
- [Error Tracking](error-tracking.md) - Exception handling
- [API Reference](../api/interactor-interface.md) - Complete API docs
- [Performance Tips](../advanced/performance.md) - Optimization guide