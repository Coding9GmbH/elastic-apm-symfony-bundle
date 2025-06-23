<?php

namespace App\MessageHandler;

use App\Message\ProcessDataMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;
use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

#[AsMessageHandler]
class ProcessDataMessageHandler
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?ElasticApmInteractorInterface $apm = null
    ) {}

    public function __invoke(ProcessDataMessage $message): void
    {
        $messageId = $message->getId();
        $data = $message->getData();
        
        // Add context to current transaction
        if ($this->apm) {
            $this->apm->setCustomContext([
                'message_id' => $messageId,
                'message_data' => $data
            ]);
        }
        
        // Main processing span that contains all operations
        $processingSpan = null;
        if ($this->apm && $this->apm->isEnabled()) {
            $processingSpan = $this->apm->startSpan('Process message', 'app', 'handler');
        }
        
        try {
            // Span 1: Validate message (child of processing span)
            if ($this->apm && $this->apm->isEnabled()) {
                $validationSpan = $this->apm->startSpan('Validate message', 'app', 'validation', null, $processingSpan);
                try {
                    usleep(20000); // 20ms
                    
                    // Check if message contains "fail"
                    if (isset($data['data']) && str_contains($data['data'], 'fail')) {
                        throw new \InvalidArgumentException('Message validation failed: contains "fail" keyword');
                    }
                } finally {
                    $this->apm->stopSpan($validationSpan);
                }
            }
            
            // Span 2: Database operation (child of processing span)
            if ($this->apm && $this->apm->isEnabled()) {
                $dbSpan = $this->apm->startSpan('SELECT * FROM users WHERE id = ?', 'db', 'mysql', null, $processingSpan);
                $this->apm->setSpanCustomData($dbSpan, [
                    'db' => [
                        'type' => 'mysql',
                        'statement' => 'SELECT * FROM users WHERE id = ?',
                        'instance' => 'app_db'
                    ]
                ]);
                try {
                    usleep(50000); // 50ms - simulate DB query
                } finally {
                    $this->apm->stopSpan($dbSpan);
                }
            }
            
            // Span 3: External API call (child of processing span)
            if ($this->apm && $this->apm->isEnabled()) {
                $apiSpan = $this->apm->startSpan('POST external-api.example.com', 'external', 'http', null, $processingSpan);
                $this->apm->setSpanCustomData($apiSpan, [
                    'http' => [
                        'url' => 'https://external-api.example.com/process',
                        'method' => 'POST'
                    ]
                ]);
                try {
                    usleep(80000); // 80ms - simulate API call
                    
                    // Simulate API error for "error" messages
                    if (isset($data['data']) && str_contains($data['data'], 'error')) {
                        throw new \RuntimeException('External API returned 500: Service unavailable');
                    }
                } finally {
                    $this->apm->stopSpan($apiSpan);
                }
            }
            
            // Span 4: Cache operation (child of processing span)
            if ($this->apm && $this->apm->isEnabled()) {
                $cacheSpan = $this->apm->startSpan('redis.set', 'cache', 'redis', null, $processingSpan);
                $this->apm->setSpanCustomData($cacheSpan, [
                    'cache' => [
                        'key' => "processed:$messageId",
                        'ttl' => 3600
                    ]
                ]);
                try {
                    usleep(10000); // 10ms
                } finally {
                    $this->apm->stopSpan($cacheSpan);
                }
            }
            
            if ($this->logger) {
                $this->logger->info('Message processed successfully', [
                    'message_id' => $messageId,
                    'data' => $data,
                    'duration_ms' => 160
                ]);
            }
        } finally {
            // Always stop the main processing span
            if ($processingSpan && $this->apm) {
                $this->apm->stopSpan($processingSpan);
            }
        }
    }
}