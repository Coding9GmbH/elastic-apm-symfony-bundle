<?php

namespace ElasticApmBundle\Interactor;

use ElasticApmBundle\Model\Transaction;
use ElasticApmBundle\Model\Span;

/**
 * Adaptive interactor that can switch between different interactors at runtime
 */
class AdaptiveInteractor implements ElasticApmInteractorInterface
{
    private ElasticApmInteractorInterface $interactor;
    
    public function __construct(ElasticApmInteractorInterface $defaultInteractor)
    {
        $this->interactor = $defaultInteractor;
    }
    
    /**
     * Switch to a different interactor at runtime
     */
    public function setInteractor(ElasticApmInteractorInterface $interactor): void
    {
        $this->interactor = $interactor;
    }
    
    public function startTransaction(string $name, string $type): Transaction
    {
        return $this->interactor->startTransaction($name, $type);
    }
    
    public function stopTransaction(?Transaction $transaction, ?int $result = null): void
    {
        $this->interactor->stopTransaction($transaction, $result);
    }
    
    public function startSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?Transaction $transaction = null
    ): Span {
        return $this->interactor->startSpan($name, $type, $subtype, $transaction);
    }
    
    public function stopSpan(Span $span): void
    {
        $this->interactor->stopSpan($span);
    }
    
    public function captureException(\Throwable $exception): void
    {
        $this->interactor->captureException($exception);
    }
    
    public function captureError(string $message, array $context = []): void
    {
        $this->interactor->captureError($message, $context);
    }
    
    public function setTransactionCustomData(Transaction $transaction, array $data): void
    {
        $this->interactor->setTransactionCustomData($transaction, $data);
    }
    
    public function setSpanCustomData(Span $span, array $data): void
    {
        $this->interactor->setSpanCustomData($span, $data);
    }
    
    public function setUserContext(array $context): void
    {
        $this->interactor->setUserContext($context);
    }
    
    public function setCustomContext(array $context): void
    {
        $this->interactor->setCustomContext($context);
    }
    
    public function setLabels(array $labels): void
    {
        $this->interactor->setLabels($labels);
    }
    
    public function flush(): void
    {
        $this->interactor->flush();
    }
    
    public function isEnabled(): bool
    {
        return $this->interactor->isEnabled();
    }
    
    public function isRecording(): bool
    {
        return $this->interactor->isRecording();
    }
    
    public function getCurrentTransaction(): ?Transaction
    {
        return $this->interactor->getCurrentTransaction();
    }
    
    public function getTraceContext(): array
    {
        return $this->interactor->getTraceContext();
    }
    
    public function captureCurrentSpan(
        string $name,
        string $type,
        callable $callback,
        array $context = []
    ): mixed {
        return $this->interactor->captureCurrentSpan($name, $type, $callback, $context);
    }
}