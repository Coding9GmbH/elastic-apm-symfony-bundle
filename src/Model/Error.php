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


namespace Coding9\ElasticApmBundle\Model;

class Error
{
    private string $id;
    private \Throwable $exception;
    private ?Transaction $transaction = null;
    private ?string $traceId = null;
    private array $context = [];
    private float $timestamp;
    
    public function __construct(\Throwable $exception, ?Transaction $transaction = null)
    {
        $this->id = $this->generateId();
        $this->exception = $exception;
        $this->transaction = $transaction;
        $this->timestamp = microtime(true);
        
        if ($transaction) {
            $this->traceId = $transaction->getTraceId();
        }
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getException(): \Throwable
    {
        return $this->exception;
    }
    
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => (int)($this->timestamp * 1000000), // microseconds
            'trace_id' => $this->traceId,
            'transaction_id' => $this->transaction ? $this->transaction->getId() : null,
            'parent_id' => $this->transaction ? $this->transaction->getId() : null,
            'exception' => [
                'message' => $this->exception->getMessage(),
                'type' => get_class($this->exception),
                'code' => $this->exception->getCode(),
                'stacktrace' => $this->formatStackTrace($this->exception),
            ],
            'context' => $this->context,
        ];
    }
    
    private function formatStackTrace(\Throwable $exception): array
    {
        $frames = [];
        
        foreach ($exception->getTrace() as $frame) {
            $frames[] = [
                'filename' => $frame['file'] ?? 'unknown',
                'lineno' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'classname' => $frame['class'] ?? null,
                'method' => isset($frame['class']) ? $frame['class'] . '::' . $frame['function'] : $frame['function'] ?? 'unknown',
            ];
        }
        
        return $frames;
    }
    
    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}