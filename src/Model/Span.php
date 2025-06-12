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

class Span
{
    private string $id;
    private string $name;
    private string $type;
    private ?string $subtype = null;
    private ?string $action = null;
    private ?Transaction $transaction = null;
    private ?float $startTime = null;
    private ?float $duration = null;
    private array $context = [];
    private array $stackTrace = [];
    
    public function __construct(string $name, string $type, ?Transaction $transaction = null)
    {
        $this->id = $this->generateId();
        $this->name = $name;
        $this->type = $type;
        $this->transaction = $transaction;
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function setType(string $type): void
    {
        $this->type = $type;
    }
    
    public function setSubtype(?string $subtype): void
    {
        $this->subtype = $subtype;
    }
    
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }
    
    public function setAction(?string $action): void
    {
        $this->action = $action;
    }
    
    public function getAction(): ?string
    {
        return $this->action;
    }
    
    public function start(): void
    {
        $this->startTime = microtime(true);
    }
    
    public function stop(): void
    {
        if ($this->startTime !== null) {
            $this->duration = (microtime(true) - $this->startTime) * 1000; // Convert to milliseconds
        }
    }
    
    public function getDuration(): ?float
    {
        return $this->duration;
    }
    
    public function setMeta(array $meta): void
    {
        $this->context = array_merge($this->context, ['custom' => $meta]);
    }
    
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function setStackTrace(array $stackTrace): void
    {
        $this->stackTrace = $stackTrace;
    }
    
    public function getStackTrace(): array
    {
        return $this->stackTrace;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction ? $this->transaction->getId() : null,
            'parent_id' => $this->transaction ? $this->transaction->getId() : null,
            'trace_id' => $this->transaction ? $this->transaction->getTraceId() : null,
            'name' => $this->name,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'action' => $this->action,
            'timestamp' => $this->startTime ? (int)($this->startTime * 1000000) : null, // microseconds
            'duration' => $this->duration,
            'context' => $this->context,
            'stacktrace' => $this->stackTrace,
        ];
    }
    
    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}