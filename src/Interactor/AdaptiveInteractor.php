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
        ?Transaction $transaction = null,
        ?Span $parentSpan = null
    ): Span {
        return $this->interactor->startSpan($name, $type, $subtype, $transaction, $parentSpan);
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