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


namespace ElasticApmBundle\Client;

use ElasticApmBundle\Model\Transaction;
use ElasticApmBundle\Model\Span;
use ElasticApmBundle\Model\Error;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ApmClient
{
    private array $config;
    private LoggerInterface $logger;
    private array $queue = [];
    private ?array $metadata = null;
    private static ?int $lastFailureTime = null;
    private static ?int $consecutiveFailures = 0;
    
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->initializeMetadata();
    }
    
    public function sendTransaction(Transaction $transaction): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }
        
        $this->queue[] = ['transaction' => $transaction->toArray()];
        
        if (count($this->queue) >= ($this->config['queue_size'] ?? 100)) {
            $this->flushAsync();
        }
    }
    
    public function sendSpan(Span $span): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }
        
        $this->queue[] = ['span' => $span->toArray()];
        
        if (count($this->queue) >= ($this->config['queue_size'] ?? 100)) {
            $this->flushAsync();
        }
    }
    
    public function sendError(Error $error): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        
        $this->queue[] = ['error' => $error->toArray()];
        
        // Errors are sent immediately
        $this->flush();
    }
    
    public function flush(): void
    {
        if (empty($this->queue) || !$this->isEnabled() || $this->isCircuitBreakerOpen()) {
            return;
        }
        
        $payload = $this->buildPayload();
        $this->sendToApmServer($payload);
        $this->queue = [];
    }
    
    private function isCircuitBreakerOpen(): bool
    {
        // Simple circuit breaker: stop trying for 60s after 3 consecutive failures
        if (self::$consecutiveFailures >= 3) {
            $backoffTime = 60; // seconds
            if (time() - self::$lastFailureTime < $backoffTime) {
                $this->logger->debug('APM circuit breaker open - skipping send');
                return true;
            } else {
                // Reset after backoff period
                self::$consecutiveFailures = 0;
            }
        }
        return false;
    }
    
    private function flushAsync(): void
    {
        // For high-performance apps, defer sending to shutdown
        if ($this->config['defer_to_shutdown'] ?? false) {
            // Register shutdown function to send data after response is sent
            if (!function_exists('fastcgi_finish_request')) {
                // For non-FPM environments, just send normally
                $this->flush();
            } else {
                // Data will be sent in shutdown handler (after response sent)
                register_shutdown_function([$this, 'flush']);
            }
        } else {
            $this->flush();
        }
    }
    
    private function shouldSample(): bool
    {
        $sampleRate = $this->config['transactions']['sample_rate'] ?? 1.0;
        return mt_rand() / mt_getrandmax() <= $sampleRate;
    }
    
    private function buildPayload(): string
    {
        $ndjson = json_encode(['metadata' => $this->metadata]) . "\n";
        
        foreach ($this->queue as $item) {
            $ndjson .= json_encode($item) . "\n";
        }
        
        return $ndjson;
    }
    
    private function sendToApmServer(string $payload): void
    {
        $url = rtrim($this->config['server']['url'] ?? 'http://localhost:8200', '/') . '/intake/v2/events';
        
        $headers = [
            'Content-Type: application/x-ndjson',
            'User-Agent: elastic-apm-symfony/1.0',
        ];
        
        // Add authentication
        if (!empty($this->config['server']['secret_token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['server']['secret_token'];
        } elseif (!empty($this->config['server']['api_key'])) {
            $headers[] = 'Authorization: ApiKey ' . $this->config['server']['api_key'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => gzencode($payload), // Add compression
            CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Encoding: gzip']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2, // Reduce timeout to 2s max
            CURLOPT_CONNECTTIMEOUT => 1, // 1s connection timeout
            CURLOPT_SSL_VERIFYPEER => $this->config['server']['verify_server_cert'] ?? true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Use HTTP/2
            CURLOPT_TCP_KEEPALIVE => 1, // Enable keep-alive
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->error('Failed to send APM data: ' . $error);
            $this->recordFailure();
        } elseif ($httpCode >= 400) {
            $this->logger->error('APM server returned error: HTTP ' . $httpCode . ' - ' . $response);
            $this->recordFailure();
        } else {
            $this->logger->debug('Successfully sent APM data');
            $this->recordSuccess();
        }
    }
    
    private function recordFailure(): void
    {
        self::$consecutiveFailures++;
        self::$lastFailureTime = time();
    }
    
    private function recordSuccess(): void
    {
        self::$consecutiveFailures = 0;
        self::$lastFailureTime = null;
    }
    
    private function initializeMetadata(): void
    {
        $this->metadata = [
            'service' => [
                'name' => $this->config['service']['name'] ?? 'symfony-app',
                'version' => $this->config['service']['version'] ?? '1.0.0',
                'environment' => $this->config['service']['environment'] ?? 'production',
                'language' => [
                    'name' => 'php',
                    'version' => PHP_VERSION,
                ],
                'runtime' => [
                    'name' => 'php',
                    'version' => PHP_VERSION,
                ],
                'framework' => [
                    'name' => 'symfony',
                    'version' => $this->config['service']['framework']['version'] ?? 'unknown',
                ],
            ],
            'process' => [
                'pid' => getmypid(),
            ],
            'system' => [
                'hostname' => gethostname(),
                'architecture' => php_uname('m'),
                'platform' => php_uname('s'),
            ],
        ];
    }
    
    private function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }
}