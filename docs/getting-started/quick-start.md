# Quick Start Guide

Get your Symfony application monitored with APM in 5 minutes!

## 1. Minimal Setup

After [installation](installation.md), you're already tracking:

- ✅ HTTP requests and responses
- ✅ Exceptions and errors
- ✅ Console commands
- ✅ Database queries (if using Doctrine)

No code changes required!

## 2. View Your First Traces

1. **Make Some Requests:**
   ```bash
   # Visit your application
   curl http://localhost:8000/
   
   # Run a console command
   php bin/console cache:clear
   ```

2. **Check Kibana/APM UI:**
   - Open http://localhost:5601
   - Navigate to APM
   - Select your service name

## 3. Add Custom Instrumentation

### Basic Span Tracking

```php
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

class ProductService
{
    public function __construct(
        private ElasticApmInteractorInterface $apm
    ) {}
    
    public function processOrder(Order $order): void
    {
        // Automatically tracked as a span
        $this->apm->captureCurrentSpan('Process order', 'business', function() use ($order) {
            // Your business logic here
            $this->validateOrder($order);
            $this->calculatePricing($order);
            $this->saveOrder($order);
        });
    }
}
```

### Add Context Information

```php
public function processPayment(Payment $payment): void
{
    // Add custom context
    $this->apm->setCustomContext([
        'payment_id' => $payment->getId(),
        'amount' => $payment->getAmount(),
        'currency' => $payment->getCurrency(),
    ]);
    
    // Process payment...
}
```

## 4. Track Message Queue Processing

If using Symfony Messenger:

```php
use ElasticApmBundle\Helper\MessageHandlerApmTrait;

#[AsMessageHandler]
class SendEmailHandler
{
    use MessageHandlerApmTrait;
    
    public function __invoke(SendEmail $message): void
    {
        // Automatically tracked!
        $this->sendEmail($message->getTo(), $message->getSubject());
    }
}
```

## 5. Monitor Errors

Exceptions are automatically captured, but you can also manually report:

```php
try {
    $this->riskyOperation();
} catch (\Exception $e) {
    // Report but don't stop execution
    $this->apm->captureException($e);
    
    // Handle gracefully
    $this->fallbackOperation();
}
```

## 6. Performance Tips

### Use Sampling in Production

```yaml
# config/packages/elastic_apm.yaml
elastic_apm:
    transactions:
        sample_rate: 0.1  # Sample 10% of requests
```

### Disable in Development

```yaml
# config/packages/dev/elastic_apm.yaml
elastic_apm:
    enabled: false  # Or use blackhole interactor
```

## 7. Common Patterns

### Repository Pattern

```php
use ElasticApmBundle\Helper\ApmTracingTrait;

class ProductRepository
{
    use ApmTracingTrait;
    
    public function findByCriteria(array $criteria): array
    {
        return $this->apmTrace('db.query', 'elasticsearch', function() use ($criteria) {
            return $this->elasticSearch->search($criteria);
        });
    }
}
```

### HTTP Client Tracking

```php
class ApiClient
{
    use ApmTracingTrait;
    
    public function fetchUserData(int $userId): array
    {
        return $this->apmTrace('http.request', 'external', function() use ($userId) {
            return $this->httpClient->get("/users/{$userId}");
        }, ['http.url' => "/users/{$userId}"]);
    }
}
```

## 8. Debugging

### Check if APM is Working

```php
// In any controller or service
dd($this->apm->isEnabled()); // Should return true
```

### View Current Transaction

```php
$transaction = $this->apm->getCurrentTransaction();
dump($transaction->getName());
dump($transaction->getType());
```

### Enable Debug Logging

```yaml
# config/packages/dev/elastic_apm.yaml
elastic_apm:
    logging: true
```

Then check logs:
```bash
tail -f var/log/dev.log | grep -i apm
```

## Next Steps

- 📖 [Automatic Instrumentation](../usage/automatic-instrumentation.md) - What's tracked by default
- 🔧 [Configuration Options](../configuration/basic.md) - Customize behavior
- 🚀 [Advanced Usage](../usage/manual-instrumentation.md) - Deep dive into features
- 🔒 [Security Best Practices](../security/best-practices.md) - Production recommendations

## Example Application

Here's a complete example controller:

```php
<?php

namespace App\Controller;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private ElasticApmInteractorInterface $apm,
        private ProductRepository $products,
        private OrderService $orderService
    ) {}
    
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        // Add user context
        if ($user = $this->getUser()) {
            $this->apm->setUserContext([
                'id' => $user->getId(),
                'username' => $user->getUsername(),
            ]);
        }
        
        // Track custom spans
        $stats = $this->apm->captureCurrentSpan('Calculate stats', 'business', function() {
            return [
                'products' => $this->products->countActive(),
                'orders' => $this->orderService->getTodaysCount(),
                'revenue' => $this->orderService->getTodaysRevenue(),
            ];
        });
        
        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
        ]);
    }
}
```

Your dashboard performance is now fully monitored! 🎉