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