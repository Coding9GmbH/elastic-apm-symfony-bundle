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
        
        // Span 1: Validate message
        if ($this->apm) {
            $this->apm->captureCurrentSpan('Validate message', 'app.internal', function() use ($data) {
                usleep(20000); // 20ms
                
                // Check if message contains "fail"
                if (isset($data['data']) && str_contains($data['data'], 'fail')) {
                    throw new \InvalidArgumentException('Message validation failed: contains "fail" keyword');
                }
            }, ['component' => 'validator']);
        }
        
        // Span 2: Database operation
        if ($this->apm) {
            $this->apm->captureCurrentSpan('SELECT * FROM users WHERE id = ?', 'db.mysql.query', function() {
                usleep(50000); // 50ms - simulate DB query
            }, [
                'db' => [
                    'type' => 'mysql',
                    'statement' => 'SELECT * FROM users WHERE id = ?',
                    'instance' => 'app_db'
                ]
            ]);
        }
        
        // Span 3: External API call
        if ($this->apm) {
            $this->apm->captureCurrentSpan('POST external-api.example.com', 'external.http', function() use ($data) {
                usleep(80000); // 80ms - simulate API call
                
                // Simulate API error for "error" messages
                if (isset($data['data']) && str_contains($data['data'], 'error')) {
                    throw new \RuntimeException('External API returned 500: Service unavailable');
                }
            }, [
                'http' => [
                    'url' => 'https://external-api.example.com/process',
                    'method' => 'POST'
                ]
            ]);
        }
        
        // Span 4: Cache operation
        if ($this->apm) {
            $this->apm->captureCurrentSpan('redis.set', 'cache.redis', function() use ($messageId) {
                usleep(10000); // 10ms
            }, [
                'cache' => [
                    'key' => "processed:$messageId",
                    'ttl' => 3600
                ]
            ]);
        }
        
        if ($this->logger) {
            $this->logger->info('Message processed successfully', [
                'message_id' => $messageId,
                'data' => $data,
                'duration_ms' => 160
            ]);
        }
    }
}