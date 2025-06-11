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


namespace ElasticApmBundle\Interactor;

use ElasticApmBundle\TransactionNamingStrategy\TransactionNamingStrategyInterface;
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
    private ?array $currentTransaction = null;
    private array $spanStack = [];
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
            'span_id' => $this->generateSpanId(),
            'operation_name' => $name,
            'start_time' => $timestamp ?? microtime(true),
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
            'trace_id' => $this->currentTransaction['trace_id'],
            'span_id' => $this->currentTransaction['span_id'],
        ]);
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

        $endTime = microtime(true);
        $duration = ($endTime - $this->currentTransaction['start_time']) * 1000000; // microseconds

        $this->currentTransaction['finish_time'] = $endTime;
        $this->currentTransaction['duration'] = $duration;

        // Add result tags
        if ($result !== null) {
            $this->currentTransaction['tags']['transaction.result'] = $result;
        }
        if ($outcome !== null) {
            $this->currentTransaction['tags']['transaction.outcome'] = $outcome;
        }

        // Send span data in OpenTracing format
        $this->sendSpan($this->currentTransaction);

        $this->logger?->debug('[OpenTracing] Ended transaction: ' . $this->currentTransaction['operation_name'], [
            'trace_id' => $this->currentTransaction['trace_id'],
            'duration_ms' => $duration / 1000,
        ]);

        $this->currentTransaction = null;
        $this->spanStack = [];
    }

    public function setTransactionContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        // Convert context to OpenTracing tags
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $this->currentTransaction['tags']["{$key}.{$subKey}"] = $this->formatTagValue($subValue);
                }
            } else {
                $this->currentTransaction['tags'][$key] = $this->formatTagValue($value);
            }
        }
    }

    public function setTransactionLabels(array $labels): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        foreach ($labels as $key => $value) {
            $this->currentTransaction['tags'][$key] = $this->formatTagValue($value);
        }
    }

    public function addTransactionLabel(string $key, $value): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $this->currentTransaction['tags'][$key] = $this->formatTagValue($value);
    }

    public function beginCurrentSpan(string $name, string $type, ?string $subType = null, ?string $action = null): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        $parentSpanId = !empty($this->spanStack) 
            ? end($this->spanStack)['span_id'] 
            : $this->currentTransaction['span_id'];

        $span = [
            'trace_id' => $this->currentTransaction['trace_id'],
            'span_id' => $this->generateSpanId(),
            'parent_span_id' => $parentSpanId,
            'operation_name' => $name,
            'start_time' => microtime(true),
            'tags' => [
                'span.kind' => 'internal',
                'component' => $type,
            ],
            'logs' => [],
        ];

        if ($subType) {
            $span['tags']['span.subtype'] = $subType;
        }
        if ($action) {
            $span['tags']['span.action'] = $action;
        }

        $this->spanStack[] = $span;
    }

    public function endCurrentSpan(): void
    {
        if (!$this->enabled || empty($this->spanStack)) {
            return;
        }

        $span = array_pop($this->spanStack);
        $endTime = microtime(true);
        $duration = ($endTime - $span['start_time']) * 1000000; // microseconds

        $span['finish_time'] = $endTime;
        $span['duration'] = $duration;

        // Send span data
        $this->sendSpan($span);
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
            // Add error tags to current span
            if (!empty($this->spanStack)) {
                $currentSpan = &$this->spanStack[count($this->spanStack) - 1];
                $currentSpan['tags']['error'] = true;
                $currentSpan['tags']['error.kind'] = get_class($e);
                $currentSpan['logs'][] = [
                    'timestamp' => microtime(true),
                    'level' => 'error',
                    'message' => $e->getMessage(),
                    'event' => 'error',
                ];
            }
            
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

        // Create error span
        $errorSpan = [
            'trace_id' => $this->currentTransaction['trace_id'] ?? $this->generateTraceId(),
            'span_id' => $this->generateSpanId(),
            'parent_span_id' => $this->currentTransaction['span_id'] ?? null,
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
            'trace_id' => $this->currentTransaction['trace_id'] ?? $this->generateTraceId(),
            'span_id' => $this->generateSpanId(),
            'parent_span_id' => $this->currentTransaction['span_id'] ?? null,
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

    public function setUserContext(?string $id = null, ?string $email = null, ?string $username = null): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        if ($id) $this->currentTransaction['tags']['user.id'] = $id;
        if ($email) $this->currentTransaction['tags']['user.email'] = $email;
        if ($username) $this->currentTransaction['tags']['user.username'] = $username;
    }

    public function setCustomContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        foreach ($context as $key => $value) {
            $this->currentTransaction['tags']["custom.{$key}"] = $this->formatTagValue($value);
        }
    }

    public function getCurrentTraceId(): ?string
    {
        return $this->currentTransaction['trace_id'] ?? null;
    }

    public function getCurrentTransactionId(): ?string
    {
        return $this->currentTransaction['span_id'] ?? null;
    }

    public function startRequestTransaction(Request $request): void
    {
        if (!$this->enabled) {
            return;
        }

        $name = $this->namingStrategy->getTransactionName($request);
        $this->beginTransaction($name, 'request');

        // Handle distributed tracing headers
        $this->handleDistributedTracing($request);

        // Set HTTP-specific tags
        $this->currentTransaction['tags'] = array_merge($this->currentTransaction['tags'], [
            'http.method' => $request->getMethod(),
            'http.url' => $request->getUri(),
            'http.route' => $request->attributes->get('_route', 'unknown'),
            'http.user_agent' => $request->headers->get('User-Agent', ''),
            'span.kind' => 'server',
        ]);
    }

    public function endRequestTransaction(Response $response): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }

        // Set response tags
        $this->currentTransaction['tags']['http.status_code'] = $response->getStatusCode();
        
        $result = (string) $response->getStatusCode();
        $outcome = $response->getStatusCode() >= 400 ? 'failure' : 'success';

        $this->endCurrentTransaction($result, $outcome);
    }

    private function handleDistributedTracing(Request $request): void
    {
        // Check for W3C Trace Context (traceparent header)
        $traceparent = $request->headers->get('traceparent');
        if ($traceparent && preg_match('/^[\da-f]{2}-([\da-f]{32})-([\da-f]{16})-[\da-f]{2}$/', $traceparent, $matches)) {
            $this->currentTransaction['trace_id'] = $matches[1];
            $this->currentTransaction['references'][] = [
                'type' => 'child_of',
                'trace_id' => $matches[1],
                'span_id' => $matches[2],
            ];
            return;
        }

        // Check for Jaeger headers
        $uberTraceId = $request->headers->get('uber-trace-id');
        if ($uberTraceId && preg_match('/^([\da-f]+):([\da-f]+):([\da-f]+):(.*)$/', $uberTraceId, $matches)) {
            $this->currentTransaction['trace_id'] = str_pad($matches[1], 32, '0', STR_PAD_LEFT);
            $this->currentTransaction['references'][] = [
                'type' => 'child_of',
                'trace_id' => str_pad($matches[1], 32, '0', STR_PAD_LEFT),
                'span_id' => str_pad($matches[2], 16, '0', STR_PAD_LEFT),
            ];
            return;
        }

        // Check for B3 headers
        $b3TraceId = $request->headers->get('x-b3-traceid');
        $b3SpanId = $request->headers->get('x-b3-spanid');
        if ($b3TraceId && $b3SpanId) {
            $this->currentTransaction['trace_id'] = str_pad($b3TraceId, 32, '0', STR_PAD_LEFT);
            $this->currentTransaction['references'][] = [
                'type' => 'child_of',
                'trace_id' => str_pad($b3TraceId, 32, '0', STR_PAD_LEFT),
                'span_id' => str_pad($b3SpanId, 16, '0', STR_PAD_LEFT),
            ];
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