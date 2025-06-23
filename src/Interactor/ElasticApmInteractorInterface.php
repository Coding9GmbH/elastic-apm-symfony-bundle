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
        ?Transaction $transaction = null,
        ?Span $parentSpan = null
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