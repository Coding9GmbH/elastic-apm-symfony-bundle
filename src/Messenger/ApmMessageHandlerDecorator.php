<?php

namespace ElasticApmBundle\Messenger;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * Decorator that automatically adds APM tracking to message handlers
 * This wraps existing handlers without requiring code changes
 */
class ApmMessageHandlerDecorator implements MessageHandlerInterface
{
    private MessageHandlerInterface $decoratedHandler;
    private ElasticApmInteractorInterface $apmInteractor;

    public function __construct(
        MessageHandlerInterface $decoratedHandler,
        ElasticApmInteractorInterface $apmInteractor
    ) {
        $this->decoratedHandler = $decoratedHandler;
        $this->apmInteractor = $apmInteractor;
    }

    public function __invoke($message, ?Envelope $envelope = null)
    {
        $messageClass = get_class($message);
        $handlerClass = get_class($this->decoratedHandler);
        
        // Create span for handler execution
        $spanName = sprintf('Handle %s', $this->getShortClassName($messageClass));
        
        return $this->apmInteractor->captureCurrentSpan(
            $spanName, 
            'message.handler', 
            function() use ($message, $envelope) {
                // Add handler context
                $this->addHandlerContext($message, $envelope);
                
                // Execute the original handler
                return ($this->decoratedHandler)($message, $envelope);
            },
            'handler',
            'process'
        );
    }

    private function addHandlerContext($message, ?Envelope $envelope): void
    {
        $messageClass = get_class($message);
        $handlerClass = get_class($this->decoratedHandler);
        
        // Set handler context
        $this->apmInteractor->setCustomContext([
            'handler' => [
                'class' => $handlerClass,
                'message_class' => $messageClass,
            ],
        ]);
        
        // Add labels
        $this->apmInteractor->addTransactionLabel('handler.class', $this->getShortClassName($handlerClass));
        $this->apmInteractor->addTransactionLabel('handler.message_class', $this->getShortClassName($messageClass));
        
        // Extract and add message-specific data
        $messageData = $this->extractMessageData($message);
        if (!empty($messageData)) {
            $this->apmInteractor->setCustomContext(['message_data' => $messageData]);
            
            // Add specific labels
            if (isset($messageData['messageType'])) {
                $this->apmInteractor->addTransactionLabel('message.type', $messageData['messageType']);
            }
            
            if (isset($messageData['messageAction'])) {
                $this->apmInteractor->addTransactionLabel('message.action', $messageData['messageAction']);
            }
            
            if (isset($messageData['transactionId'])) {
                $this->apmInteractor->addTransactionLabel('message.transaction_id', $messageData['transactionId']);
            }
        }
    }

    private function extractMessageData($message): array
    {
        $data = [];
        
        // Handle your specific message types
        if (method_exists($message, 'getTransactionId')) {
            $data['transactionId'] = (string) $message->getTransactionId();
        }
        
        if (method_exists($message, 'getMessageAction')) {
            $action = $message->getMessageAction();
            $data['messageAction'] = $action->name ?? (string) $action;
        }
        
        if (method_exists($message, 'getMessageType')) {
            $type = $message->getMessageType();
            $data['messageType'] = $type->name ?? (string) $type;
        }
        
        if (method_exists($message, 'getMessageSource')) {
            $data['messageSource'] = $message->getMessageSource();
        }
        
        // Handle command messages with data arrays
        if (method_exists($message, 'getAccountData')) {
            $accountData = $message->getAccountData();
            if (isset($accountData['properties'])) {
                $data = array_merge($data, [
                    'messageType' => $accountData['properties']['messageType'] ?? null,
                    'messageAction' => $accountData['properties']['messageAction'] ?? null,
                    'messageSource' => $accountData['properties']['messageSource'] ?? null,
                    'transactionId' => $accountData['properties']['transactionId'] ?? null,
                ]);
            }
            
            // Add entity IDs for correlation
            if (isset($accountData['fields'])) {
                $fields = $accountData['fields'];
                if (isset($fields['globalAccountId'])) {
                    $data['globalAccountId'] = $fields['globalAccountId'];
                }
                if (isset($fields['globalContactId'])) {
                    $data['globalContactId'] = $fields['globalContactId'];
                }
                if (isset($fields['accountNumber'])) {
                    $data['accountNumber'] = $fields['accountNumber'];
                }
            }
        }
        
        return array_filter($data);
    }

    private function getShortClassName(string $className): string
    {
        return substr(strrchr($className, '\\'), 1) ?: $className;
    }
}