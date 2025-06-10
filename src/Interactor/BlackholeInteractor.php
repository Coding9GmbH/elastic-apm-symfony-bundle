<?php

namespace ElasticApmBundle\Interactor;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No-op implementation of ElasticApmInteractorInterface
 * This interactor does nothing and is used when APM is disabled
 */
class BlackholeInteractor implements ElasticApmInteractorInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function beginTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        // No-op
    }

    public function beginCurrentTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        // No-op
    }

    public function endCurrentTransaction(?string $result = null, ?string $outcome = null): void
    {
        // No-op
    }

    public function setTransactionContext(array $context): void
    {
        // No-op
    }

    public function setTransactionLabels(array $labels): void
    {
        // No-op
    }

    public function beginCurrentSpan(string $name, string $type, ?string $subType = null, ?string $action = null): void
    {
        // No-op
    }

    public function endCurrentSpan(): void
    {
        // No-op
    }

    public function captureCurrentSpan(string $name, string $type, callable $callback, ?string $subType = null, ?string $action = null): mixed
    {
        return $callback();
    }

    public function captureException(\Throwable $exception): void
    {
        // No-op
    }

    public function captureError(string $type, string $message, ?string $file = null, ?int $line = null): void
    {
        // No-op
    }

    public function setUserContext(?string $id = null, ?string $email = null, ?string $username = null): void
    {
        // No-op
    }

    public function setCustomContext(array $context): void
    {
        // No-op
    }

    public function addTransactionLabel(string $key, $value): void
    {
        // No-op
    }

    public function getCurrentTraceId(): ?string
    {
        return null;
    }

    public function getCurrentTransactionId(): ?string
    {
        return null;
    }

    public function startRequestTransaction(Request $request): void
    {
        // No-op
    }

    public function endRequestTransaction(Response $response): void
    {
        // No-op
    }
}