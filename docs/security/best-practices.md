# Security Best Practices

This guide covers security best practices for using the Elastic APM Symfony Bundle in production environments.

## Authentication and Authorization

### Secure APM Server Connection

Always use authentication in production:

```yaml
# config/packages/prod/elastic_apm.yaml
elastic_apm:
    server:
        url: 'https://apm.example.com'  # Always use HTTPS
        secret_token: '%env(ELASTIC_APM_SECRET_TOKEN)%'
        # OR use API key (recommended)
        api_key: '%env(ELASTIC_APM_API_KEY)%'
        verify_server_cert: true  # Verify SSL certificate
```

### API Key vs Secret Token

**Recommended: Use API Keys**

```bash
# Generate API key in Elasticsearch
curl -X POST "localhost:9200/_security/api_key" -H 'Content-Type: application/json' -d'
{
  "name": "symfony-app-apm",
  "role_descriptors": {
    "apm_writer": {
      "cluster": ["monitor"],
      "index": [
        {
          "names": ["apm-*"],
          "privileges": ["create_index", "create", "write"]
        }
      ]
    }
  }
}'
```

### Certificate Verification

```yaml
elastic_apm:
    server:
        verify_server_cert: true
        ca_cert_path: '/path/to/ca-certificate.crt'
        # For mutual TLS
        client_cert_path: '/path/to/client-cert.crt'
        client_key_path: '/path/to/client-key.pem'
```

## Sensitive Data Protection

### Field Sanitization

Configure fields to be sanitized:

```yaml
elastic_apm:
    transactions:
        sanitize_field_names:
            # Exact matches
            - 'password'
            - 'passwd'
            - 'pwd'
            - 'secret'
            - 'token'
            - 'apikey'
            - 'api_key'
            - 'access_token'
            - 'auth_token'
            - 'credentials'
            - 'mysql_pwd'
            - 'stripe_token'
            - 'card_number'
            - 'cvv'
            - 'cvc'
            # Patterns (regex)
            - '/.*session.*/'
            - '/.*credit.*/'
            - '/.*debit.*/'
            - '/.*bank.*/'
```

### Header Redaction

Sensitive headers are automatically redacted:

```yaml
elastic_apm:
    security:
        redact_headers:
            - 'authorization'
            - 'cookie'
            - 'set-cookie'
            - 'x-api-key'
            - 'x-auth-token'
            - 'x-csrf-token'
            - 'x-forwarded-for'  # May contain IP addresses
```

### Request Body Handling

Control what request data is captured:

```yaml
elastic_apm:
    transactions:
        capture_body: 'off'  # Options: off, errors, transactions, all
        capture_headers: false  # Disable header capture entirely
```

### Custom Data Sanitization

Implement custom sanitization:

```php
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

class PaymentService
{
    public function processPayment(array $paymentData): void
    {
        // Sanitize before sending to APM
        $sanitizedData = $this->sanitizePaymentData($paymentData);
        
        $this->apm->setCustomContext([
            'payment' => $sanitizedData
        ]);
    }
    
    private function sanitizePaymentData(array $data): array
    {
        return [
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'method' => $data['method'],
            // Never include:
            // - card_number
            // - cvv
            // - expiry_date
        ];
    }
}
```

## Message Queue Security

### Disable Message Body Capture

For sensitive messages:

```yaml
elastic_apm:
    messaging:
        capture_message_body: false
        capture_message_headers: false
        # Only capture safe metadata
        safe_message_properties:
            - 'message_id'
            - 'timestamp'
            - 'retry_count'
```

### Message Handler Security

```php
#[AsMessageHandler]
class ProcessPaymentHandler
{
    use MessageHandlerApmTrait;
    
    public function __invoke(ProcessPayment $message): void
    {
        // Don't log sensitive payment details
        $this->apm->setCustomContext([
            'payment_id' => $message->getId(),
            // Don't include: card details, amounts, etc.
        ]);
    }
}
```

## Database Query Security

### Query Parameter Sanitization

```yaml
elastic_apm:
    database:
        capture_queries: true
        # Automatically replaces parameters with ?
        sanitize_queries: true
        # Further sanitization
        query_filters:
            - '/INSERT INTO users.*VALUES.*/i'
            - '/password\s*=\s*\'[^\']+\'/i'
```

### Custom Query Sanitization

```php
class SecureRepository
{
    public function executeQuery(string $sql, array $params): mixed
    {
        // Log query structure, not values
        $this->apm->captureCurrentSpan('db.query', 'mysql', function() use ($sql, $params) {
            return $this->connection->executeQuery($sql, $params);
        }, [
            'db.statement' => $this->sanitizeSql($sql),
            'db.param_count' => count($params),
            // Never log actual parameter values
        ]);
    }
    
    private function sanitizeSql(string $sql): string
    {
        // Replace values with placeholders
        return preg_replace('/\b\d+\b/', '?', $sql);
    }
}
```

## Error Handling Security

### Exception Message Sanitization

```yaml
elastic_apm:
    exceptions:
        sanitize_messages: true
        message_filters:
            - '/password: .+/'
            - '/token=[\w\-]+/'
            - '/api_key=[\w\-]+/'
        # Maximum message length
        max_message_length: 1000
```

### Custom Exception Handling

```php
class SecureExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        // Sanitize exception before capture
        if ($this->containsSensitiveData($exception)) {
            $sanitized = new \RuntimeException(
                $this->sanitizeMessage($exception->getMessage()),
                $exception->getCode()
            );
            $this->apm->captureException($sanitized);
        } else {
            $this->apm->captureException($exception);
        }
    }
    
    private function sanitizeMessage(string $message): string
    {
        // Remove sensitive patterns
        return preg_replace(
            ['/password:\s*\S+/', '/token=\S+/'],
            ['password: ***', 'token=***'],
            $message
        );
    }
}
```

## User Context Security

### Safe User Context

```php
public function setUserContext(User $user): void
{
    // Only include safe user properties
    $this->apm->setUserContext([
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        // Don't include:
        // - email (PII)
        // - full name (PII)
        // - phone numbers
        // - addresses
    ]);
}
```

### GDPR Compliance

```php
class GdprCompliantApmService
{
    public function setUserContext(User $user): void
    {
        if ($user->hasConsentedToTracking()) {
            $this->apm->setUserContext([
                'id' => $user->getId(),
                'username' => $this->hashUsername($user->getUsername()),
            ]);
        } else {
            // Anonymous tracking only
            $this->apm->setUserContext([
                'id' => 'anonymous',
            ]);
        }
    }
    
    private function hashUsername(string $username): string
    {
        return substr(hash('sha256', $username . $this->salt), 0, 8);
    }
}
```

## Network Security

### Firewall Configuration

```yaml
# AWS Security Group Example
- Type: Egress
  Protocol: TCP
  Port: 443
  Destination: apm.example.com
  Description: Allow HTTPS to APM server
```

### Proxy Configuration

```yaml
elastic_apm:
    server:
        proxy:
            url: 'http://proxy.internal:3128'
            username: '%env(PROXY_USERNAME)%'
            password: '%env(PROXY_PASSWORD)%'
            # Use proxy for APM only
            no_proxy: ['localhost', '127.0.0.1', '.internal']
```

## Environment Security

### Environment Variables

```bash
# .env.prod.local (git-ignored)
ELASTIC_APM_SECRET_TOKEN="your-secret-token-here"
ELASTIC_APM_API_KEY="your-api-key-here"

# Never commit secrets to .env
```

### Kubernetes Secrets

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: apm-secrets
type: Opaque
data:
  secret-token: <base64-encoded-token>
  api-key: <base64-encoded-api-key>
```

```yaml
# deployment.yaml
env:
  - name: ELASTIC_APM_SECRET_TOKEN
    valueFrom:
      secretKeyRef:
        name: apm-secrets
        key: secret-token
```

## Access Control

### Restrict APM Endpoints

```yaml
# config/packages/security.yaml
security:
    access_control:
        # Restrict APM debug endpoints
        - { path: ^/apm/, roles: ROLE_ADMIN }
        - { path: ^/_apm/, roles: ROLE_ADMIN }
```

### Controller Security

```php
#[Route('/apm/status')]
#[IsGranted('ROLE_ADMIN')]
class ApmStatusController extends AbstractController
{
    public function status(): Response
    {
        // APM status information
    }
}
```

## Audit and Compliance

### Audit Logging

```php
class ApmAuditLogger
{
    public function logApmAccess(string $action, User $user): void
    {
        $this->logger->info('APM access', [
            'action' => $action,
            'user_id' => $user->getId(),
            'timestamp' => time(),
            'ip' => $this->requestStack->getCurrentRequest()->getClientIp(),
        ]);
    }
}
```

### Compliance Checks

```yaml
# Disable APM in specific regions
elastic_apm:
    enabled: '%env(bool:APM_ENABLED_FOR_REGION)%'
    compliance:
        gdpr_mode: true
        anonymize_ips: true
        data_retention_days: 30
```

## Security Checklist

- [ ] Use HTTPS for APM server connection
- [ ] Enable certificate verification
- [ ] Use API keys instead of secret tokens
- [ ] Configure field sanitization
- [ ] Disable body capture for sensitive endpoints
- [ ] Sanitize database queries
- [ ] Limit user context to non-PII data
- [ ] Use environment variables for secrets
- [ ] Implement proper access control
- [ ] Regular security audits
- [ ] Monitor APM access logs
- [ ] Keep APM agent updated

## Incident Response

### Disable APM Quickly

```yaml
# Emergency kill switch
elastic_apm:
    enabled: '%env(bool:APM_KILL_SWITCH)%'
```

```bash
# Disable immediately
export APM_KILL_SWITCH=false
php bin/console cache:clear --env=prod
```

### Data Purge

If sensitive data is accidentally sent:

```bash
# Delete APM indices containing sensitive data
curl -X DELETE "localhost:9200/apm-*-2024.01.15"
```

## Next Steps

- [RUM Configuration](rum-configuration.md) - Secure browser monitoring
- [Sensitive Data](sensitive-data.md) - Detailed data handling
- [Configuration](../configuration/advanced.md) - Security options
- [Troubleshooting](../advanced/troubleshooting.md) - Security issues