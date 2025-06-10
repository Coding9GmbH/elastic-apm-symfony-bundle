<?php

namespace ElasticApmBundle\Model;

class Transaction
{
    private string $id;
    private string $traceId;
    private string $name;
    private string $type;
    private ?float $startTime = null;
    private ?float $duration = null;
    private ?string $result = null;
    private array $context = [];
    private array $labels = [];
    private array $customContext = [];
    private array $userContext = [];
    private array $spans = [];
    private bool $sampled = true;
    
    public function __construct(string $name, string $type)
    {
        $this->id = $this->generateId();
        $this->traceId = $this->generateTraceId();
        $this->name = $name;
        $this->type = $type;
        $this->startTime = microtime(true);
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getTraceId(): string
    {
        return $this->traceId;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function setResult(string $result): void
    {
        $this->result = $result;
    }
    
    public function getResult(): ?string
    {
        return $this->result;
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
    
    public function setUserContext(array $context): void
    {
        $this->userContext = array_merge($this->userContext, $context);
    }
    
    public function getUserContext(): array
    {
        return $this->userContext;
    }
    
    public function setCustomContext(array $context): void
    {
        $this->customContext = array_merge($this->customContext, $context);
    }
    
    public function getCustomContext(): array
    {
        return $this->customContext;
    }
    
    public function setLabels(array $labels): void
    {
        $this->labels = array_merge($this->labels, $labels);
    }
    
    public function getLabels(): array
    {
        return $this->labels;
    }
    
    public function setMeta(array $meta): void
    {
        $this->customContext = array_merge($this->customContext, $meta);
    }
    
    public function addSpan(Span $span): void
    {
        $this->spans[] = $span;
    }
    
    public function getSpans(): array
    {
        return $this->spans;
    }
    
    public function setSampled(bool $sampled): void
    {
        $this->sampled = $sampled;
    }
    
    public function isSampled(): bool
    {
        return $this->sampled;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'trace_id' => $this->traceId,
            'parent_id' => null,
            'name' => $this->name,
            'type' => $this->type,
            'timestamp' => (int)($this->startTime * 1000000), // microseconds
            'duration' => $this->duration,
            'result' => $this->result,
            'sampled' => $this->sampled,
            'context' => [
                'user' => $this->userContext,
                'custom' => $this->customContext,
                'tags' => $this->labels,
            ],
            'spans' => array_map(fn($span) => $span->toArray(), $this->spans),
            'span_count' => [
                'started' => count($this->spans),
                'dropped' => 0,
            ],
        ];
    }
    
    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
    
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}