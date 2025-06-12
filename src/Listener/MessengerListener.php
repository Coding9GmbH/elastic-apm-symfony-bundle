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


namespace Coding9\ElasticApmBundle\Listener;

use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class MessengerListener implements EventSubscriberInterface
{
    private ElasticApmInteractorInterface $interactor;
    private array $activeTransactions = [];

    public function __construct(ElasticApmInteractorInterface $interactor)
    {
        $this->interactor = $interactor;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => ['onMessageReceived', 2048],
            WorkerMessageHandledEvent::class => ['onMessageHandled', -2048],
            WorkerMessageFailedEvent::class => ['onMessageFailed', -2048],
            SendMessageToTransportsEvent::class => ['onMessageSent', 0],
        ];
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();
        $receivedStamp = $envelope->last(ReceivedStamp::class);
        
        $messageClass = get_class($message);
        $transportName = $receivedStamp ? $receivedStamp->getTransportName() : 'unknown';
        
        // Create transaction name
        $transactionName = $this->getMessageTransactionName($message, $transportName, 'consume');
        
        // Start transaction
        $this->interactor->beginCurrentTransaction($transactionName, 'messaging');
        
        // Set message context
        $this->setMessageContext($envelope, $transportName, 'consume');
        
        // Store transaction reference
        $messageId = spl_object_hash($envelope);
        $this->activeTransactions[$messageId] = [
            'start_time' => microtime(true),
            'message_class' => $messageClass,
            'transport' => $transportName,
            'operation' => 'consume',
        ];
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $messageId = spl_object_hash($envelope);
        
        if (isset($this->activeTransactions[$messageId])) {
            $transactionData = $this->activeTransactions[$messageId];
            
            // Add success labels
            $this->interactor->addTransactionLabel('message.status', 'handled');
            $this->interactor->addTransactionLabel('message.handler_count', count($event->getHandlerDescriptors()));
            
            // End transaction with success
            $this->interactor->endCurrentTransaction('handled', 'success');
            
            unset($this->activeTransactions[$messageId]);
        }
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $messageId = spl_object_hash($envelope);
        $throwable = $event->getThrowable();
        
        if (isset($this->activeTransactions[$messageId])) {
            // Capture the exception
            $this->interactor->captureException($throwable);
            
            // Add failure labels
            $this->interactor->addTransactionLabel('message.status', 'failed');
            $this->interactor->addTransactionLabel('message.retry_count', $event->getRetryCount());
            $this->interactor->addTransactionLabel('message.will_retry', $event->willRetry());
            
            // End transaction with failure
            $this->interactor->endCurrentTransaction('failed', 'failure');
            
            unset($this->activeTransactions[$messageId]);
        }
    }

    public function onMessageSent(SendMessageToTransportsEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();
        
        // Create span for message sending
        $messageClass = get_class($message);
        $spanName = sprintf('Send %s', $this->getShortClassName($messageClass));
        
        $this->interactor->captureCurrentSpan($spanName, 'messaging', function() use ($envelope, $event) {
            // Set sending context
            $this->setSendingContext($envelope, $event->getSenders());
        }, 'amqp', 'send');
    }

    private function setMessageContext($envelope, string $transportName, string $operation): void
    {
        $message = $envelope->getMessage();
        $messageClass = get_class($message);
        
        // Basic message context
        $context = [
            'message' => [
                'class' => $messageClass,
                'transport' => $transportName,
                'operation' => $operation,
            ],
        ];
        
        // Add bus name if available
        $busStamp = $envelope->last(BusNameStamp::class);
        if ($busStamp) {
            $context['message']['bus'] = $busStamp->getBusName();
        }
        
        // Add received stamp info
        $receivedStamp = $envelope->last(ReceivedStamp::class);
        if ($receivedStamp) {
            $context['message']['received_at'] = $receivedStamp->getReceivedAt()->format('c');
        }
        
        // Extract message-specific data
        $messageData = $this->extractMessageData($message);
        if (!empty($messageData)) {
            $context['message']['data'] = $messageData;
        }
        
        $this->interactor->setTransactionContext($context);
        
        // Add labels
        $this->interactor->addTransactionLabel('message.class', $messageClass);
        $this->interactor->addTransactionLabel('message.transport', $transportName);
        $this->interactor->addTransactionLabel('message.operation', $operation);
        
        if ($busStamp) {
            $this->interactor->addTransactionLabel('message.bus', $busStamp->getBusName());
        }
    }

    private function setSendingContext($envelope, array $senders): void
    {
        $message = $envelope->getMessage();
        
        // Set sending context
        $this->interactor->setCustomContext([
            'message_class' => get_class($message),
            'transport_count' => count($senders),
            'transports' => array_keys($senders),
        ]);
        
        // Extract message data for sending
        $messageData = $this->extractMessageData($message);
        if (!empty($messageData)) {
            $this->interactor->setCustomContext(['message_data' => $messageData]);
        }
    }

    private function extractMessageData($message): array
    {
        $data = [];
        
        // Handle your specific message types
        if (method_exists($message, 'getTransactionId')) {
            $data['transaction_id'] = (string) $message->getTransactionId();
        }
        
        if (method_exists($message, 'getMessageAction')) {
            $data['action'] = $message->getMessageAction()->name ?? (string) $message->getMessageAction();
        }
        
        if (method_exists($message, 'getMessageType')) {
            $data['type'] = $message->getMessageType()->name ?? (string) $message->getMessageType();
        }
        
        if (method_exists($message, 'getMessageSource')) {
            $data['source'] = $message->getMessageSource();
        }
        
        // Handle command messages
        if (method_exists($message, 'getAccountData')) {
            $accountData = $message->getAccountData();
            if (isset($accountData['properties'])) {
                $data = array_merge($data, [
                    'message_type' => $accountData['properties']['messageType'] ?? null,
                    'message_action' => $accountData['properties']['messageAction'] ?? null,
                    'message_source' => $accountData['properties']['messageSource'] ?? null,
                    'transaction_id' => $accountData['properties']['transactionId'] ?? null,
                ]);
            }
        }
        
        // Add any other message-specific extractors here
        return array_filter($data);
    }

    private function getMessageTransactionName($message, string $transportName, string $operation): string
    {
        $messageClass = $this->getShortClassName(get_class($message));
        
        // Try to get more specific name from message data
        $messageData = $this->extractMessageData($message);
        
        if (isset($messageData['message_type'], $messageData['message_action'])) {
            return sprintf('%s %s.%s', 
                ucfirst($operation), 
                $messageData['message_type'], 
                $messageData['message_action']
            );
        }
        
        if (isset($messageData['type'], $messageData['action'])) {
            return sprintf('%s %s.%s', 
                ucfirst($operation), 
                $messageData['type'], 
                $messageData['action']
            );
        }
        
        return sprintf('%s %s via %s', ucfirst($operation), $messageClass, $transportName);
    }

    private function getShortClassName(string $className): string
    {
        return substr(strrchr($className, '\\'), 1) ?: $className;
    }
}