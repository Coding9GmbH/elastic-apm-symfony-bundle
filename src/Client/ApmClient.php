<?php

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
    
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->initializeMetadata();
    }
    
    public function sendTransaction(Transaction $transaction): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        
        $this->queue[] = ['transaction' => $transaction->toArray()];
        
        if (count($this->queue) >= ($this->config['queue_size'] ?? 100)) {
            $this->flush();
        }
    }
    
    public function sendSpan(Span $span): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        
        $this->queue[] = ['span' => $span->toArray()];
        
        if (count($this->queue) >= ($this->config['queue_size'] ?? 100)) {
            $this->flush();
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
        if (empty($this->queue) || !$this->isEnabled()) {
            return;
        }
        
        $payload = $this->buildPayload();
        $this->sendToApmServer($payload);
        $this->queue = [];
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
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['server']['timeout'] ?? 10,
            CURLOPT_SSL_VERIFYPEER => $this->config['server']['verify_server_cert'] ?? true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->error('Failed to send APM data: ' . $error);
        } elseif ($httpCode >= 400) {
            $this->logger->error('APM server returned error: HTTP ' . $httpCode . ' - ' . $response);
        } else {
            $this->logger->debug('Successfully sent APM data');
        }
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