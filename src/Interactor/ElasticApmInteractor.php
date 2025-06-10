<?php

namespace ElasticApmBundle\Interactor;

use ElasticApmBundle\Model\Transaction;
use ElasticApmBundle\Model\Span;
use ElasticApmBundle\Model\Error;
use ElasticApmBundle\Client\ApmClient;

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
            
            if ($transaction === $this->currentTransaction) {
                $this->currentTransaction = null;
            }
        }
    }
    
    public function startSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?Transaction $transaction = null
    ): Span {
        $transaction = $transaction ?? $this->currentTransaction;
        
        $span = new Span($name, $type, $transaction);
        
        if ($subtype !== null) {
            $span->setSubtype($subtype);
        }
        
        $span->start();
        
        return $span;
    }
    
    public function stopSpan(Span $span): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $span->stop();
        
        // Send the span to APM server
        $this->client->sendSpan($span);
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