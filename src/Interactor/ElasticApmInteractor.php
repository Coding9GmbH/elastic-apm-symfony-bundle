<?php

namespace ElasticApmBundle\Interactor;

use ElasticApmBundle\TransactionNamingStrategy\TransactionNamingStrategyInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ElasticApmInteractor implements ElasticApmInteractorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private array $config;
    private bool $enabled;
    private ?array $currentTransaction = null;
    private array $spans = [];
    private TransactionNamingStrategyInterface $namingStrategy;

    public function __construct(
        array $config,
        TransactionNamingStrategyInterface $namingStrategy,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->enabled = $config['enabled'] ?? true;
        $this->namingStrategy = $namingStrategy;
        $this->logger = $logger;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function beginTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->currentTransaction = [
            'trace_id' => $this->generateTraceId(),
            'id' => $this->generateId(),
            'name' => $name,
            'type' => $type,
            'timestamp' => $timestamp ?? $this->getCurrentTimestamp(),
            'context' => [],
            'labels' => [],
            'spans' => [],
        ];

        $this->logger?->debug('[ElasticAPM] Started transaction: ' . $name);
    }

    public function beginCurrentTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        $this->beginTransaction($name, $type, $timestamp);
    }

    public function endCurrentTransaction(?string $result = null, ?string $outcome = null): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $duration = (microtime(true) - ($this->currentTransaction['timestamp'] / 1000000)) * 1000;

        $this->currentTransaction['duration'] = $duration;
        $this->currentTransaction['result'] = $result ?? 'success';
        $this->currentTransaction['outcome'] = $outcome ?? 'success';
        $this->currentTransaction['span_count'] = [
            'started' => count($this->spans),
            'dropped' => 0
        ];

        // Send transaction data
        $this->send($this->buildPayload());

        $this->logger?->debug('[ElasticAPM] Ended transaction: ' . $this->currentTransaction['name']);

        $this->currentTransaction = null;
        $this->spans = [];
    }

    public function setTransactionContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction['context'] = array_merge(
            $this->currentTransaction['context'] ?? [],
            $context
        );
    }

    public function setTransactionLabels(array $labels): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction['labels'] = array_merge(
            $this->currentTransaction['labels'] ?? [],
            $labels
        );
    }

    public function addTransactionLabel(string $key, $value): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction['labels'][$key] = $value;
    }

    public function beginCurrentSpan(string $name, string $type, ?string $subType = null, ?string $action = null): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $span = [
            'id' => $this->generateId(),
            'transaction_id' => $this->currentTransaction['id'],
            'trace_id' => $this->currentTransaction['trace_id'],
            'name' => $name,
            'type' => $type,
            'subtype' => $subType,
            'action' => $action,
            'timestamp' => $this->getCurrentTimestamp(),
        ];

        $this->spans[] = $span;
    }

    public function endCurrentSpan(): void
    {
        if (!$this->enabled || empty($this->spans)) {
            return;
        }

        $span = array_pop($this->spans);
        if ($span) {
            $duration = (microtime(true) - ($span['timestamp'] / 1000000)) * 1000;
            $span['duration'] = $duration;
            
            // Add to transaction spans
            $this->currentTransaction['spans'][] = $span;
        }
    }

    public function captureCurrentSpan(string $name, string $type, callable $callback, ?string $subType = null, ?string $action = null): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        $this->beginCurrentSpan($name, $type, $subType, $action);
        
        try {
            $result = $callback();
            $this->endCurrentSpan();
            return $result;
        } catch (\Throwable $e) {
            $this->endCurrentSpan();
            $this->captureException($e);
            throw $e;
        }
    }

    public function captureException(\Throwable $exception): void
    {
        if (!$this->enabled) {
            return;
        }

        $error = [
            'error' => [
                'timestamp' => $this->getCurrentTimestamp(),
                'id' => $this->generateId(),
                'trace_id' => $this->currentTransaction['trace_id'] ?? $this->generateTraceId(),
                'transaction_id' => $this->currentTransaction['id'] ?? null,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'type' => get_class($exception),
                    'stacktrace' => $this->formatStackTrace($exception),
                ],
            ],
        ];

        // Send error immediately
        $this->send($this->buildErrorPayload($error));

        $this->logger?->debug('[ElasticAPM] Captured exception: ' . $exception->getMessage());
    }

    public function captureError(string $type, string $message, ?string $file = null, ?int $line = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $error = [
            'error' => [
                'timestamp' => $this->getCurrentTimestamp(),
                'id' => $this->generateId(),
                'trace_id' => $this->currentTransaction['trace_id'] ?? $this->generateTraceId(),
                'transaction_id' => $this->currentTransaction['id'] ?? null,
                'log' => [
                    'message' => $message,
                    'level' => $type,
                    'param_message' => $message,
                ],
                'culprit' => $file ? "{$file}:{$line}" : null,
            ],
        ];

        // Send error immediately
        $this->send($this->buildErrorPayload($error));

        $this->logger?->debug("[ElasticAPM] Captured error: {$type} - {$message}");
    }

    public function setUserContext(?string $id = null, ?string $email = null, ?string $username = null): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction['context']['user'] = array_filter([
            'id' => $id,
            'email' => $email,
            'username' => $username,
        ]);
    }

    public function setCustomContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction['context']['custom'] = array_merge(
            $this->currentTransaction['context']['custom'] ?? [],
            $context
        );
    }

    public function getCurrentTraceId(): ?string
    {
        return $this->currentTransaction['trace_id'] ?? null;
    }

    public function getCurrentTransactionId(): ?string
    {
        return $this->currentTransaction['id'] ?? null;
    }

    public function startRequestTransaction(Request $request): void
    {
        if (!$this->enabled) {
            return;
        }

        $name = $this->namingStrategy->getTransactionName($request);
        $this->beginTransaction($name, 'request');

        // Handle distributed tracing
        $traceparent = $request->headers->get('traceparent');
        if ($traceparent && preg_match('/^[\da-f]{2}-([\da-f]{32})-([\da-f]{16})-[\da-f]{2}$/', $traceparent, $matches)) {
            $this->currentTransaction['trace_id'] = $matches[1];
            $this->currentTransaction['parent_id'] = $matches[2];
        }

        // Set request context
        $this->setTransactionContext([
            'request' => [
                'method' => $request->getMethod(),
                'url' => [
                    'full' => $request->getUri(),
                    'hostname' => $request->getHost(),
                    'pathname' => $request->getPathInfo(),
                    'protocol' => $request->getScheme(),
                    'search' => $request->getQueryString(),
                ],
                'headers' => $this->sanitizeHeaders($request->headers->all()),
                'http_version' => $request->getProtocolVersion(),
                'socket' => [
                    'remote_address' => $request->getClientIp(),
                ],
            ],
        ]);

        // Set custom context
        $this->setCustomContext([
            'route' => $request->attributes->get('_route'),
            'controller' => $request->attributes->get('_controller'),
        ]);
    }

    public function endRequestTransaction(Response $response): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        // Set response context
        $this->setTransactionContext([
            'response' => [
                'status_code' => $response->getStatusCode(),
                'headers' => $this->sanitizeHeaders($response->headers->all()),
            ],
        ]);

        $result = (string) $response->getStatusCode();
        $outcome = $response->getStatusCode() >= 400 ? 'failure' : 'success';

        $this->endCurrentTransaction($result, $outcome);
    }

    private function buildPayload(): string
    {
        $metadata = [
            'metadata' => [
                'service' => [
                    'name' => $this->config['service']['name'],
                    'version' => $this->config['service']['version'],
                    'environment' => $this->config['service']['environment'],
                    'language' => [
                        'name' => 'php',
                        'version' => PHP_VERSION,
                    ],
                    'runtime' => [
                        'name' => 'php',
                        'version' => PHP_VERSION,
                    ],
                    'agent' => [
                        'name' => 'elastic-apm-symfony',
                        'version' => '1.0.0',
                    ],
                ],
                'process' => [
                    'pid' => getmypid(),
                ],
                'system' => [
                    'hostname' => gethostname(),
                    'platform' => php_uname('s'),
                ],
            ],
        ];

        $transaction = ['transaction' => $this->currentTransaction];

        // Build NDJSON payload
        $payload = json_encode($metadata) . "\n" . json_encode($transaction) . "\n";

        // Add spans if any
        foreach ($this->currentTransaction['spans'] ?? [] as $span) {
            $payload .= json_encode(['span' => $span]) . "\n";
        }

        return $payload;
    }

    private function buildErrorPayload(array $error): string
    {
        $metadata = [
            'metadata' => [
                'service' => [
                    'name' => $this->config['service']['name'],
                    'version' => $this->config['service']['version'],
                    'environment' => $this->config['service']['environment'],
                ],
            ],
        ];

        return json_encode($metadata) . "\n" . json_encode($error) . "\n";
    }

    private function send(string $payload): void
    {
        $url = rtrim($this->config['server']['url'], '/') . '/intake/v2/events';

        $headers = ['Content-Type: application/x-ndjson'];

        if (!empty($this->config['server']['api_key'])) {
            $headers[] = 'Authorization: ApiKey ' . $this->config['server']['api_key'];
        } elseif (!empty($this->config['server']['secret_token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['server']['secret_token'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger?->error('[ElasticAPM] CURL error: ' . $error);
        } elseif ($httpCode === 202) {
            $this->logger?->info('[ElasticAPM] Successfully sent APM data');
        } else {
            $this->logger?->error('[ElasticAPM] Failed with HTTP ' . $httpCode . ': ' . $response);
        }
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function getCurrentTimestamp(): int
    {
        return (int)(microtime(true) * 1000000);
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        $sanitized = [];

        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);
            if (!in_array($lowerKey, $sensitive)) {
                $sanitized[$key] = $values;
            }
        }

        return $sanitized;
    }

    private function formatStackTrace(\Throwable $exception): array
    {
        $frames = [];
        
        foreach ($exception->getTrace() as $frame) {
            $frames[] = [
                'filename' => $frame['file'] ?? null,
                'lineno' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'classname' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];
        }

        return $frames;
    }
}