<?php
// Copyright (c) 2025 Coding 9 GmbH
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of
// this software and associated documentation files (the "Software"), to deal in
// the Software without restriction, including without limitation the rights to
// use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
// the Software, and to permit persons to whom the Software is furnished to do so,
// subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
// COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
// IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
// CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.


namespace Coding9\ElasticApmBundle\Interactor;

use Coding9\ElasticApmBundle\Model\Span;
use Coding9\ElasticApmBundle\Model\Transaction;
use Coding9\ElasticApmBundle\TransactionNamingStrategy\TransactionNamingStrategyInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OpenTracing-compatible interactor
 * Implements distributed tracing following OpenTracing specification patterns
 */
class OpenTracingInteractor implements ElasticApmInteractorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private array $config;
    private bool $enabled;
    private ?Transaction $currentTransaction = null;
    private array $spanStack = [];
    private array $openTracingData = [];
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

    public function isRecording(): bool
    {
        return $this->enabled && $this->currentTransaction !== null;
    }

    public function startTransaction(string $name, string $type): Transaction
    {
        if (!$this->enabled) {
            return new Transaction($name, $type);
        }

        $transaction = new Transaction($name, $type);
        $this->currentTransaction = $transaction;
        
        // Store OpenTracing-specific data
        $this->openTracingData[$transaction->getId()] = [
            'trace_id' => $transaction->getTraceId(),
            'span_id' => $transaction->getId(),
            'operation_name' => $name,
            'start_time' => microtime(true),
            'tags' => [
                'component' => 'symfony',
                'transaction.type' => $type,
                'service.name' => $this->config['service']['name'],
                'service.version' => $this->config['service']['version'],
                'service.environment' => $this->config['service']['environment'],
            ],
            'logs' => [],
            'baggage' => [],
            'references' => [],
        ];

        $this->logger?->debug('[OpenTracing] Started transaction: ' . $name, [
            'trace_id' => $transaction->getTraceId(),
            'span_id' => $transaction->getId(),
        ]);

        return $transaction;
    }

    public function stopTransaction(?Transaction $transaction, ?int $result = null): void
    {
        if (!$this->enabled || !$transaction) {
            return;
        }

        $transaction->stop();
        
        if ($result !== null) {
            $transaction->setResult((string)$result);
        }

        if (isset($this->openTracingData[$transaction->getId()])) {
            $data = &$this->openTracingData[$transaction->getId()];
            $endTime = microtime(true);
            $duration = ($endTime - $data['start_time']) * 1000000; // microseconds

            $data['finish_time'] = $endTime;
            $data['duration'] = $duration;

            // Add result tags
            if ($result !== null) {
                $data['tags']['transaction.result'] = (string)$result;
            }

            // Send span data in OpenTracing format
            $this->sendSpan($data);

            $this->logger?->debug('[OpenTracing] Ended transaction: ' . $transaction->getName(), [
                'trace_id' => $transaction->getTraceId(),
                'duration_ms' => $duration / 1000,
            ]);

            unset($this->openTracingData[$transaction->getId()]);
        }

        if ($this->currentTransaction === $transaction) {
            $this->currentTransaction = null;
            $this->spanStack = [];
        }
    }

    public function startSpan(string $name, string $type, ?string $subtype = null, ?Transaction $transaction = null): Span
    {
        if (!$this->enabled) {
            return new Span($name, $type, $transaction);
        }

        $transaction = $transaction ?? $this->currentTransaction;
        $span = new Span($name, $type, $transaction);
        
        if ($subtype) {
            $span->setSubtype($subtype);
        }

        $parentSpanId = !empty($this->spanStack) 
            ? end($this->spanStack)->getId() 
            : ($transaction ? $transaction->getId() : null);

        // Store OpenTracing-specific data
        $this->openTracingData[$span->getId()] = [
            'trace_id' => $transaction ? $transaction->getTraceId() : $this->generateTraceId(),
            'span_id' => $span->getId(),
            'parent_span_id' => $parentSpanId,
            'operation_name' => $name,
            'start_time' => microtime(true),
            'tags' => [
                'span.kind' => 'internal',
                'component' => $type,
            ],
            'logs' => [],
        ];

        if ($subtype) {
            $this->openTracingData[$span->getId()]['tags']['span.subtype'] = $subtype;
        }

        $this->spanStack[] = $span;
        
        return $span;
    }

    public function stopSpan(Span $span): void
    {
        if (!$this->enabled) {
            return;
        }

        $span->stop();

        if (isset($this->openTracingData[$span->getId()])) {
            $data = &$this->openTracingData[$span->getId()];
            $endTime = microtime(true);
            $duration = ($endTime - $data['start_time']) * 1000000; // microseconds

            $data['finish_time'] = $endTime;
            $data['duration'] = $duration;

            // Send span data
            $this->sendSpan($data);

            unset($this->openTracingData[$span->getId()]);
        }

        // Remove from stack
        $this->spanStack = array_filter($this->spanStack, fn($s) => $s !== $span);
    }

    public function captureCurrentSpan(string $name, string $type, callable $callback, array $context = []): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        $span = $this->startSpan($name, $type, $context['subtype'] ?? null);
        
        try {
            $result = $callback();
            $this->stopSpan($span);
            return $result;
        } catch (\Throwable $e) {
            // Add error tags to span
            if (isset($this->openTracingData[$span->getId()])) {
                $this->openTracingData[$span->getId()]['tags']['error'] = true;
                $this->openTracingData[$span->getId()]['tags']['error.kind'] = get_class($e);
                $this->openTracingData[$span->getId()]['logs'][] = [
                    'timestamp' => microtime(true),
                    'level' => 'error',
                    'message' => $e->getMessage(),
                    'event' => 'error',
                ];
            }
            
            $this->stopSpan($span);
            $this->captureException($e);
            throw $e;
        }
    }

    public function beginTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        $this->startTransaction($name, $type);
    }

    public function beginCurrentTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        $this->beginTransaction($name, $type, $timestamp);
    }

    public function endCurrentTransaction(?string $result = null, ?string $outcome = null): void
    {
        if ($this->currentTransaction) {
            $this->stopTransaction($this->currentTransaction, $result ? (int)$result : null);
        }
    }

    public function beginCurrentSpan(string $name, string $type, ?string $subType = null, ?string $action = null): void
    {
        $span = $this->startSpan($name, $type, $subType);
        if ($action && isset($this->openTracingData[$span->getId()])) {
            $this->openTracingData[$span->getId()]['tags']['span.action'] = $action;
        }
    }

    public function endCurrentSpan(): void
    {
        if (!empty($this->spanStack)) {
            $span = array_pop($this->spanStack);
            $this->stopSpan($span);
        }
    }

    public function captureException(\Throwable $exception): void
    {
        if (!$this->enabled) {
            return;
        }

        // Create error span
        $errorSpan = [
            'trace_id' => $this->currentTransaction ? $this->currentTransaction->getTraceId() : $this->generateTraceId(),
            'span_id' => $this->generateSpanId(),
            'parent_span_id' => $this->currentTransaction ? $this->currentTransaction->getId() : null,
            'operation_name' => 'error',
            'start_time' => microtime(true),
            'finish_time' => microtime(true),
            'duration' => 0,
            'tags' => [
                'error' => true,
                'error.kind' => get_class($exception),
                'error.object' => get_class($exception),
                'component' => 'exception',
                'span.kind' => 'internal',
            ],
            'logs' => [
                [
                    'timestamp' => microtime(true),
                    'level' => 'error',
                    'message' => $exception->getMessage(),
                    'event' => 'error',
                    'error.object' => get_class($exception),
                    'stack' => $exception->getTraceAsString(),
                ],
            ],
        ];

        $this->sendSpan($errorSpan);

        $this->logger?->debug('[OpenTracing] Captured exception: ' . $exception->getMessage(), [
            'trace_id' => $errorSpan['trace_id'],
            'exception_class' => get_class($exception),
        ]);
    }

    public function captureError(string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $errorSpan = [
            'trace_id' => $this->currentTransaction ? $this->currentTransaction->getTraceId() : $this->generateTraceId(),
            'span_id' => $this->generateSpanId(),
            'parent_span_id' => $this->currentTransaction ? $this->currentTransaction->getId() : null,
            'operation_name' => 'error',
            'start_time' => microtime(true),
            'finish_time' => microtime(true),
            'duration' => 0,
            'tags' => [
                'error' => true,
                'error.kind' => $context['type'] ?? 'error',
                'component' => 'error',
                'span.kind' => 'internal',
            ],
            'logs' => [
                [
                    'timestamp' => microtime(true),
                    'level' => 'error',
                    'message' => $message,
                    'event' => 'error',
                    'file' => $context['file'] ?? null,
                    'line' => $context['line'] ?? null,
                    'context' => $context,
                ],
            ],
        ];

        $this->sendSpan($errorSpan);

        $this->logger?->debug("[OpenTracing] Captured error: {$message}", $context);
    }

    public function setTransactionContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction->setContext($context);

        // Convert context to OpenTracing tags
        if (isset($this->openTracingData[$this->currentTransaction->getId()])) {
            foreach ($context as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $this->openTracingData[$this->currentTransaction->getId()]['tags']["{$key}.{$subKey}"] = $this->formatTagValue($subValue);
                    }
                } else {
                    $this->openTracingData[$this->currentTransaction->getId()]['tags'][$key] = $this->formatTagValue($value);
                }
            }
        }
    }

    public function setTransactionLabels(array $labels): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction->setLabels($labels);

        if (isset($this->openTracingData[$this->currentTransaction->getId()])) {
            foreach ($labels as $key => $value) {
                $this->openTracingData[$this->currentTransaction->getId()]['tags'][$key] = $this->formatTagValue($value);
            }
        }
    }

    public function addTransactionLabel(string $key, $value): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction->addLabel($key, $value);

        if (isset($this->openTracingData[$this->currentTransaction->getId()])) {
            $this->openTracingData[$this->currentTransaction->getId()]['tags'][$key] = $this->formatTagValue($value);
        }
    }

    public function setTransactionCustomData(Transaction $transaction, array $data): void
    {
        if (!$this->enabled) {
            return;
        }

        $transaction->setCustomContext($data);

        if (isset($this->openTracingData[$transaction->getId()])) {
            foreach ($data as $key => $value) {
                $this->openTracingData[$transaction->getId()]['tags']["custom.{$key}"] = $this->formatTagValue($value);
            }
        }
    }

    public function setSpanCustomData(Span $span, array $data): void
    {
        if (!$this->enabled) {
            return;
        }

        $span->setContext($data);

        if (isset($this->openTracingData[$span->getId()])) {
            foreach ($data as $key => $value) {
                $this->openTracingData[$span->getId()]['tags']["custom.{$key}"] = $this->formatTagValue($value);
            }
        }
    }

    public function setUserContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction->setUserContext($context);

        if (isset($this->openTracingData[$this->currentTransaction->getId()])) {
            if (isset($context['id'])) {
                $this->openTracingData[$this->currentTransaction->getId()]['tags']['user.id'] = $context['id'];
            }
            if (isset($context['email'])) {
                $this->openTracingData[$this->currentTransaction->getId()]['tags']['user.email'] = $context['email'];
            }
            if (isset($context['username'])) {
                $this->openTracingData[$this->currentTransaction->getId()]['tags']['user.username'] = $context['username'];
            }
        }
    }

    public function setCustomContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction->setCustomContext($context);

        if (isset($this->openTracingData[$this->currentTransaction->getId()])) {
            foreach ($context as $key => $value) {
                $this->openTracingData[$this->currentTransaction->getId()]['tags']["custom.{$key}"] = $this->formatTagValue($value);
            }
        }
    }

    public function setLabels(array $labels): void
    {
        $this->setTransactionLabels($labels);
    }

    public function getCurrentTraceId(): ?string
    {
        return $this->currentTransaction ? $this->currentTransaction->getTraceId() : null;
    }

    public function getCurrentTransactionId(): ?string
    {
        return $this->currentTransaction ? $this->currentTransaction->getId() : null;
    }

    public function getCurrentTransaction(): ?Transaction
    {
        return $this->currentTransaction;
    }

    public function getTraceContext(): array
    {
        if (!$this->currentTransaction) {
            return [];
        }

        return [
            'trace_id' => $this->currentTransaction->getTraceId(),
            'transaction_id' => $this->currentTransaction->getId(),
            'parent_span_id' => !empty($this->spanStack) ? end($this->spanStack)->getId() : null,
        ];
    }

    public function startRequestTransaction(Request $request): void
    {
        if (!$this->enabled) {
            return;
        }

        $name = $this->namingStrategy->getTransactionName($request);
        $transaction = $this->startTransaction($name, 'request');

        // Handle distributed tracing headers
        $this->handleDistributedTracing($request, $transaction);

        // Set HTTP-specific tags
        if (isset($this->openTracingData[$transaction->getId()])) {
            $this->openTracingData[$transaction->getId()]['tags'] = array_merge(
                $this->openTracingData[$transaction->getId()]['tags'],
                [
                    'http.method' => $request->getMethod(),
                    'http.url' => $request->getUri(),
                    'http.route' => $request->attributes->get('_route', 'unknown'),
                    'http.user_agent' => $request->headers->get('User-Agent', ''),
                    'span.kind' => 'server',
                ]
            );
        }
    }

    public function endRequestTransaction(Response $response): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        // Set response tags
        if (isset($this->openTracingData[$this->currentTransaction->getId()])) {
            $this->openTracingData[$this->currentTransaction->getId()]['tags']['http.status_code'] = $response->getStatusCode();
        }
        
        $result = $response->getStatusCode();
        $this->stopTransaction($this->currentTransaction, $result);
    }

    public function flush(): void
    {
        // OpenTracing sends data immediately, so nothing to flush
        $this->logger?->debug('[OpenTracing] Flush called (no-op for OpenTracing)');
    }

    private function handleDistributedTracing(Request $request, Transaction $transaction): void
    {
        // Check for W3C Trace Context (traceparent header)
        $traceparent = $request->headers->get('traceparent');
        if ($traceparent && preg_match('/^[\da-f]{2}-([\da-f]{32})-([\da-f]{16})-[\da-f]{2}$/', $traceparent, $matches)) {
            $transaction->setTraceId($matches[1]);
            if (isset($this->openTracingData[$transaction->getId()])) {
                $this->openTracingData[$transaction->getId()]['trace_id'] = $matches[1];
                $this->openTracingData[$transaction->getId()]['references'][] = [
                    'type' => 'child_of',
                    'trace_id' => $matches[1],
                    'span_id' => $matches[2],
                ];
            }
            return;
        }

        // Check for Jaeger headers
        $uberTraceId = $request->headers->get('uber-trace-id');
        if ($uberTraceId && preg_match('/^([\da-f]+):([\da-f]+):([\da-f]+):(.*)$/', $uberTraceId, $matches)) {
            $traceId = str_pad($matches[1], 32, '0', STR_PAD_LEFT);
            $transaction->setTraceId($traceId);
            if (isset($this->openTracingData[$transaction->getId()])) {
                $this->openTracingData[$transaction->getId()]['trace_id'] = $traceId;
                $this->openTracingData[$transaction->getId()]['references'][] = [
                    'type' => 'child_of',
                    'trace_id' => $traceId,
                    'span_id' => str_pad($matches[2], 16, '0', STR_PAD_LEFT),
                ];
            }
            return;
        }

        // Check for B3 headers
        $b3TraceId = $request->headers->get('x-b3-traceid');
        $b3SpanId = $request->headers->get('x-b3-spanid');
        if ($b3TraceId && $b3SpanId) {
            $traceId = str_pad($b3TraceId, 32, '0', STR_PAD_LEFT);
            $transaction->setTraceId($traceId);
            if (isset($this->openTracingData[$transaction->getId()])) {
                $this->openTracingData[$transaction->getId()]['trace_id'] = $traceId;
                $this->openTracingData[$transaction->getId()]['references'][] = [
                    'type' => 'child_of',
                    'trace_id' => $traceId,
                    'span_id' => str_pad($b3SpanId, 16, '0', STR_PAD_LEFT),
                ];
            }
        }
    }

    private function sendSpan(array $span): void
    {
        // Convert to Jaeger/OpenTracing format and send
        $jaegerSpan = $this->convertToJaegerFormat($span);
        
        $url = rtrim($this->config['server']['url'], '/') . '/api/traces';
        
        $payload = json_encode([
            'data' => [
                [
                    'traceID' => $span['trace_id'],
                    'spans' => [$jaegerSpan],
                    'processes' => [
                        'p1' => [
                            'serviceName' => $this->config['service']['name'],
                            'tags' => [
                                ['key' => 'hostname', 'type' => 'string', 'value' => gethostname()],
                                ['key' => 'ip', 'type' => 'string', 'value' => $_SERVER['SERVER_ADDR'] ?? 'unknown'],
                                ['key' => 'version', 'type' => 'string', 'value' => $this->config['service']['version']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->sendHttpRequest($url, $payload);
    }

    private function convertToJaegerFormat(array $span): array
    {
        $tags = [];
        foreach ($span['tags'] as $key => $value) {
            $tags[] = [
                'key' => $key,
                'type' => is_bool($value) ? 'bool' : (is_numeric($value) ? 'number' : 'string'),
                'value' => $value,
            ];
        }

        $logs = [];
        foreach ($span['logs'] as $log) {
            $fields = [];
            foreach ($log as $key => $value) {
                $fields[] = [
                    'key' => $key,
                    'type' => 'string',
                    'value' => (string) $value,
                ];
            }
            $logs[] = [
                'timestamp' => (int) (($log['timestamp'] ?? microtime(true)) * 1000000),
                'fields' => $fields,
            ];
        }

        return [
            'traceID' => $span['trace_id'],
            'spanID' => $span['span_id'],
            'parentSpanID' => $span['parent_span_id'] ?? '',
            'operationName' => $span['operation_name'],
            'startTime' => (int) ($span['start_time'] * 1000000),
            'duration' => (int) ($span['duration'] ?? 0),
            'tags' => $tags,
            'logs' => $logs,
            'processID' => 'p1',
            'references' => $span['references'] ?? [],
        ];
    }

    private function sendHttpRequest(string $url, string $payload): void
    {
        $headers = ['Content-Type: application/json'];

        if (!empty($this->config['server']['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['server']['api_key'];
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
            $this->logger?->error('[OpenTracing] CURL error: ' . $error);
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            $this->logger?->info('[OpenTracing] Successfully sent trace data');
        } else {
            $this->logger?->error('[OpenTracing] Failed with HTTP ' . $httpCode . ': ' . $response);
        }
    }

    private function formatTagValue($value): string|bool|int|float
    {
        if (is_bool($value) || is_numeric($value)) {
            return $value;
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}