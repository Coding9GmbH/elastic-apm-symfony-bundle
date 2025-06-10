<?php

namespace ElasticApmBundle\Interactor;

use ElasticApmBundle\Model\Transaction;
use ElasticApmBundle\Model\Span;

interface ElasticApmInteractorInterface
{
    /**
     * Start a new transaction
     */
    public function startTransaction(string $name, string $type): Transaction;
    
    /**
     * Stop a transaction
     */
    public function stopTransaction(?Transaction $transaction, ?int $result = null): void;
    
    /**
     * Start a new span
     */
    public function startSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?Transaction $transaction = null
    ): Span;
    
    /**
     * Stop a span
     */
    public function stopSpan(Span $span): void;
    
    /**
     * Capture an exception
     */
    public function captureException(\Throwable $exception): void;
    
    /**
     * Capture a custom error
     */
    public function captureError(string $message, array $context = []): void;
    
    /**
     * Set custom data on a transaction
     */
    public function setTransactionCustomData(Transaction $transaction, array $data): void;
    
    /**
     * Set custom data on a span
     */
    public function setSpanCustomData(Span $span, array $data): void;
    
    /**
     * Set user context
     */
    public function setUserContext(array $context): void;
    
    /**
     * Set custom context
     */
    public function setCustomContext(array $context): void;
    
    /**
     * Set labels
     */
    public function setLabels(array $labels): void;
    
    /**
     * Flush all queued data
     */
    public function flush(): void;
    
    /**
     * Check if APM is enabled
     */
    public function isEnabled(): bool;
    
    /**
     * Check if the current transaction is being recorded
     */
    public function isRecording(): bool;
    
    /**
     * Get the current transaction
     */
    public function getCurrentTransaction(): ?Transaction;
    
    /**
     * Get trace context for distributed tracing
     */
    public function getTraceContext(): array;
    
    /**
     * Capture a span with a callback
     */
    public function captureCurrentSpan(
        string $name,
        string $type,
        callable $callback,
        array $context = []
    ): mixed;
}