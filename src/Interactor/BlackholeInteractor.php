<?php

namespace ElasticApmBundle\Interactor;

use ElasticApmBundle\Model\Transaction;
use ElasticApmBundle\Model\Span;

/**
 * No-op implementation of the APM interactor for testing or disabled state
 */
class BlackholeInteractor implements ElasticApmInteractorInterface
{
    public function startTransaction(string $name, string $type): Transaction
    {
        // Return a real transaction object that won't be sent anywhere
        return new Transaction($name, $type);
    }
    
    public function stopTransaction(?Transaction $transaction, ?int $result = null): void
    {
        // No-op
    }
    
    public function startSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?Transaction $transaction = null
    ): Span {
        // Return a real span object that won't be sent anywhere
        $span = new Span($name, $type, $transaction);
        if ($subtype !== null) {
            $span->setSubtype($subtype);
        }
        return $span;
    }
    
    public function stopSpan(Span $span): void
    {
        // No-op
    }
    
    public function captureException(\Throwable $exception): void
    {
        // No-op
    }
    
    public function captureError(string $message, array $context = []): void
    {
        // No-op
    }
    
    public function setTransactionCustomData(Transaction $transaction, array $data): void
    {
        // No-op
    }
    
    public function setSpanCustomData(Span $span, array $data): void
    {
        // No-op
    }
    
    public function setUserContext(array $context): void
    {
        // No-op
    }
    
    public function setCustomContext(array $context): void
    {
        // No-op
    }
    
    public function setLabels(array $labels): void
    {
        // No-op
    }
    
    public function flush(): void
    {
        // No-op
    }
    
    public function isEnabled(): bool
    {
        return false;
    }
    
    public function isRecording(): bool
    {
        return false;
    }
    
    public function getCurrentTransaction(): ?Transaction
    {
        return null;
    }
    
    public function getTraceContext(): array
    {
        return [];
    }
    
    public function captureCurrentSpan(
        string $name,
        string $type,
        callable $callback,
        array $context = []
    ): mixed {
        // Just execute the callback without any APM tracking
        return $callback();
    }
}