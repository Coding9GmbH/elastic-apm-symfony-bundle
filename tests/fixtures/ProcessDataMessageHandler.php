<?php

namespace App\MessageHandler;

use App\Message\ProcessDataMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class ProcessDataMessageHandler
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {}

    public function __invoke(ProcessDataMessage $message): void
    {
        // Simulate some processing time
        usleep(100000); // 100ms
        
        $data = $message->getData();
        
        if ($this->logger) {
            $this->logger->info('Processing message', [
                'message_id' => $message->getId(),
                'data' => $data
            ]);
        }
        
        // Simulate some work
        $result = array_merge($data, [
            'processed_at' => time(),
            'result' => 'success'
        ]);
        
        // Random chance of error for testing
        if (rand(1, 10) === 1) {
            throw new \RuntimeException('Random processing error for APM testing');
        }
    }
}