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


namespace ElasticApmBundle\Helper;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

/**
 * Trait for message handlers to easily add APM tracking
 * This provides convenience methods for tracking message processing operations
 */
trait MessageHandlerApmTrait
{
    protected ?ElasticApmInteractorInterface $apmInteractor = null;

    public function setApmInteractor(ElasticApmInteractorInterface $interactor): void
    {
        $this->apmInteractor = $interactor;
    }

    /**
     * Track a message processing operation
     */
    protected function apmTrackMessageOperation(string $operation, callable $callback, array $messageData = []): mixed
    {
        if ($this->apmInteractor === null) {
            return $callback();
        }

        $spanName = $this->buildOperationSpanName($operation, $messageData);
        
        return $this->apmInteractor->captureCurrentSpan($spanName, 'message.process', function() use ($callback, $messageData) {
            // Add operation-specific context
            if (!empty($messageData)) {
                $this->apmInteractor->setCustomContext(['operation_data' => $messageData]);
            }
            
            return $callback();
        }, 'handler', $operation);
    }

    /**
     * Track database operations within message processing
     */
    protected function apmTrackDatabaseOperation(string $operation, callable $callback, ?string $entityType = null): mixed
    {
        if ($this->apmInteractor === null) {
            return $callback();
        }

        $spanName = $entityType 
            ? sprintf('DB %s %s', $operation, $entityType)
            : sprintf('DB %s', $operation);
            
        return $this->apmInteractor->captureCurrentSpan($spanName, 'db', $callback, 'mysql', strtolower($operation));
    }

    /**
     * Track external API calls from message handlers
     */
    protected function apmTrackExternalCall(string $service, string $operation, callable $callback): mixed
    {
        if ($this->apmInteractor === null) {
            return $callback();
        }

        $spanName = sprintf('External %s.%s', $service, $operation);
        
        return $this->apmInteractor->captureCurrentSpan($spanName, 'external', $callback, 'http', $operation);
    }

    /**
     * Track validation operations
     */
    protected function apmTrackValidation(string $validationType, callable $callback, ?string $entityType = null): mixed
    {
        if ($this->apmInteractor === null) {
            return $callback();
        }

        $spanName = $entityType 
            ? sprintf('Validate %s %s', $validationType, $entityType)
            : sprintf('Validate %s', $validationType);
            
        return $this->apmInteractor->captureCurrentSpan($spanName, 'validation', $callback, 'form', $validationType);
    }

    /**
     * Track file operations
     */
    protected function apmTrackFileOperation(string $operation, string $fileName, callable $callback): mixed
    {
        if ($this->apmInteractor === null) {
            return $callback();
        }

        $spanName = sprintf('File %s: %s', $operation, basename($fileName));
        
        return $this->apmInteractor->captureCurrentSpan($spanName, 'storage', $callback, 's3', strtolower($operation));
    }

    /**
     * Add message-specific labels to the current transaction
     */
    protected function apmAddMessageLabels(array $messageData): void
    {
        if ($this->apmInteractor === null) {
            return;
        }

        // Add common message labels
        if (isset($messageData['messageType'])) {
            $this->apmInteractor->addTransactionLabel('message.type', $messageData['messageType']);
        }
        
        if (isset($messageData['messageAction'])) {
            $this->apmInteractor->addTransactionLabel('message.action', $messageData['messageAction']);
        }
        
        if (isset($messageData['messageSource'])) {
            $this->apmInteractor->addTransactionLabel('message.source', $messageData['messageSource']);
        }
        
        if (isset($messageData['transactionId'])) {
            $this->apmInteractor->addTransactionLabel('message.transaction_id', $messageData['transactionId']);
        }

        // Add entity-specific labels
        if (isset($messageData['globalAccountId'])) {
            $this->apmInteractor->addTransactionLabel('entity.account_id', $messageData['globalAccountId']);
        }
        
        if (isset($messageData['globalContactId'])) {
            $this->apmInteractor->addTransactionLabel('entity.contact_id', $messageData['globalContactId']);
        }
        
        if (isset($messageData['accountNumber'])) {
            $this->apmInteractor->addTransactionLabel('entity.account_number', $messageData['accountNumber']);
        }
    }

    /**
     * Set user context from message data
     */
    protected function apmSetUserContextFromMessage(array $messageData): void
    {
        if ($this->apmInteractor === null) {
            return;
        }

        $userId = null;
        $email = null;
        $username = null;

        // Extract user information from various message fields
        if (isset($messageData['globalContactId'])) {
            $userId = $messageData['globalContactId'];
        }
        
        if (isset($messageData['contactEmail'])) {
            $email = $messageData['contactEmail'];
        } elseif (isset($messageData['email'])) {
            $email = $messageData['email'];
        }
        
        if (isset($messageData['firstName'], $messageData['lastName'])) {
            $username = trim($messageData['firstName'] . ' ' . $messageData['lastName']);
        }

        if ($userId || $email || $username) {
            $this->apmInteractor->setUserContext($userId, $email, $username);
        }
    }

    /**
     * Track message processing errors with context
     */
    protected function apmTrackMessageError(\Throwable $exception, array $messageData = []): void
    {
        if ($this->apmInteractor === null) {
            return;
        }

        // Add error context
        $this->apmInteractor->setCustomContext([
            'error_context' => 'message_processing',
            'message_data' => $messageData,
        ]);

        // Capture the exception
        $this->apmInteractor->captureException($exception);
    }

    private function buildOperationSpanName(string $operation, array $messageData): string
    {
        // Try to build descriptive name from message data
        if (isset($messageData['messageType'], $messageData['messageAction'])) {
            return sprintf('%s %s.%s', 
                ucfirst($operation), 
                $messageData['messageType'], 
                $messageData['messageAction']
            );
        }

        return sprintf('Message %s', $operation);
    }
}