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

use Coding9\ElasticApmBundle\Model\Transaction;
use Coding9\ElasticApmBundle\Model\Span;
use Coding9\ElasticApmBundle\Model\Error;
use Coding9\ElasticApmBundle\Client\ApmClient;

class ElasticApmInteractor implements ElasticApmInteractorInterface
{
    private ApmClient $client;
    private ?Transaction $currentTransaction = null;
    private array $config;
    private bool $enabled;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->enabled = $config['enabled'] ?? true;
        
        $this->client = new ApmClient($config);
    }
    
    public function startTransaction(string $name, string $type): Transaction
    {
        $transaction = new Transaction($name, $type);
        
        if ($this->enabled) {
            $this->currentTransaction = $transaction;
        }
        
        return $transaction;
    }
    
    public function stopTransaction(?Transaction $transaction, ?int $result = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $transaction = $transaction ?? $this->currentTransaction;
        
        if ($transaction) {
            if ($result !== null) {
                $transaction->setResult($this->getResultString($result));
            }
            
            $transaction->stop();
            
            // Send the transaction to APM server
            $this->client->sendTransaction($transaction);
            
            // Send all spans associated with this transaction
            foreach ($transaction->getSpans() as $span) {
                $this->client->sendSpan($span);
            }
            
            if ($transaction === $this->currentTransaction) {
                $this->currentTransaction = null;
            }
        }
    }
    
    public function startSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?Transaction $transaction = null,
        ?Span $parentSpan = null
    ): Span {
        $transaction = $transaction ?? $this->currentTransaction;
        
        if (!$transaction) {
            throw new \RuntimeException('No active transaction for span');
        }
        
        $span = new Span($name, $type, $transaction);
        
        if ($subtype !== null) {
            $span->setSubtype($subtype);
        }
        
        if ($parentSpan !== null) {
            $span->setParentSpan($parentSpan);
        }
        
        $span->start();
        
        // Add span to transaction
        $transaction->addSpan($span);
        
        return $span;
    }
    
    public function stopSpan(Span $span): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $span->stop();
        
        // Don't send spans individually - they'll be sent with the transaction
    }
    
    public function captureException(\Throwable $exception): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $error = new Error($exception, $this->currentTransaction);
        $this->client->sendError($error);
    }
    
    public function captureError(string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $exception = new \Exception($message);
        $error = new Error($exception, $this->currentTransaction);
        
        if (!empty($context)) {
            $error->setContext($context);
        }
        
        $this->client->sendError($error);
    }
    
    public function setTransactionCustomData(Transaction $transaction, array $data): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $transaction->setCustomContext($data);
    }
    
    public function setSpanCustomData(Span $span, array $data): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $span->setContext($data);
    }
    
    public function setUserContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }
        
        $this->currentTransaction->setUserContext($context);
    }
    
    public function setCustomContext(array $context): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }
        
        $this->currentTransaction->setCustomContext($context);
    }
    
    public function setLabels(array $labels): void
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return;
        }
        
        $this->currentTransaction->setLabels($labels);
    }
    
    public function flush(): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->client->flush();
    }
    
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    public function isRecording(): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        // Sampling is now handled by the client
        return true;
    }
    
    public function getCurrentTransaction(): ?Transaction
    {
        return $this->currentTransaction;
    }
    
    public function getTraceContext(): array
    {
        if (!$this->enabled || !$this->currentTransaction) {
            return [];
        }
        
        // Get trace headers for distributed tracing
        $traceId = $this->currentTransaction->getTraceId();
        $transactionId = $this->currentTransaction->getId();
        
        return [
            'traceparent' => sprintf('00-%s-%s-01', $traceId, $transactionId),
            'tracestate' => '', // Could be populated with vendor-specific data
        ];
    }
    
    public function captureCurrentSpan(
        string $name,
        string $type,
        callable $callback,
        array $context = []
    ): mixed {
        $span = $this->startSpan($name, $type);
        
        if (!empty($context)) {
            $this->setSpanCustomData($span, $context);
        }
        
        try {
            $result = $callback();
            return $result;
        } catch (\Throwable $e) {
            $this->captureException($e);
            throw $e;
        } finally {
            $this->stopSpan($span);
        }
    }
    
    private function getResultString(int $httpStatusCode): string
    {
        if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
            return 'HTTP 2xx';
        } elseif ($httpStatusCode >= 300 && $httpStatusCode < 400) {
            return 'HTTP 3xx';
        } elseif ($httpStatusCode >= 400 && $httpStatusCode < 500) {
            return 'HTTP 4xx';
        } elseif ($httpStatusCode >= 500) {
            return 'HTTP 5xx';
        }
        
        return 'HTTP ' . $httpStatusCode;
    }
}