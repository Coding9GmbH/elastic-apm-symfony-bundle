<?php

namespace ElasticApmBundle\Interactor;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ElasticApmInteractorInterface
{
    /**
     * Begin a new transaction
     */
    public function beginTransaction(string $name, string $type, ?float $timestamp = null): void;

    /**
     * Begin a new transaction for the current request
     */
    public function beginCurrentTransaction(string $name, string $type, ?float $timestamp = null): void;

    /**
     * End the current transaction
     */
    public function endCurrentTransaction(?string $result = null, ?string $outcome = null): void;

    /**
     * Set transaction context
     */
    public function setTransactionContext(array $context): void;

    /**
     * Set transaction labels
     */
    public function setTransactionLabels(array $labels): void;

    /**
     * Begin a new span
     */
    public function beginCurrentSpan(string $name, string $type, ?string $subType = null, ?string $action = null): void;

    /**
     * End the current span
     */
    public function endCurrentSpan(): void;

    /**
     * Capture a span with a callback
     */
    public function captureCurrentSpan(string $name, string $type, callable $callback, ?string $subType = null, ?string $action = null): mixed;

    /**
     * Capture an exception
     */
    public function captureException(\Throwable $exception): void;

    /**
     * Capture an error
     */
    public function captureError(string $type, string $message, ?string $file = null, ?int $line = null): void;

    /**
     * Set user context
     */
    public function setUserContext(?string $id = null, ?string $email = null, ?string $username = null): void;

    /**
     * Set custom context
     */
    public function setCustomContext(array $context): void;

    /**
     * Add transaction label
     */
    public function addTransactionLabel(string $key, $value): void;

    /**
     * Get the current trace ID
     */
    public function getCurrentTraceId(): ?string;

    /**
     * Get the current transaction ID
     */
    public function getCurrentTransactionId(): ?string;

    /**
     * Check if APM is enabled
     */
    public function isEnabled(): bool;

    /**
     * Start a transaction from HTTP request
     */
    public function startRequestTransaction(Request $request): void;

    /**
     * End a transaction with HTTP response
     */
    public function endRequestTransaction(Response $response): void;
}