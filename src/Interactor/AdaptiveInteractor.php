<?php

namespace ElasticApmBundle\Interactor;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adaptive interactor that conditionally delegates to another interactor
 * based on runtime conditions (e.g., environment, request parameters)
 */
class AdaptiveInteractor implements ElasticApmInteractorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    private ElasticApmInteractorInterface $delegate;
    private bool $enabled;
    private array $enabledEnvironments;
    private float $sampleRate;

    public function __construct(
        ElasticApmInteractorInterface $delegate,
        bool $enabled = true,
        array $enabledEnvironments = ['prod', 'staging'],
        float $sampleRate = 1.0
    ) {
        $this->delegate = $delegate;
        $this->enabled = $enabled;
        $this->enabledEnvironments = $enabledEnvironments;
        $this->sampleRate = $sampleRate;
    }

    public function isEnabled(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Check if current environment is enabled
        $currentEnv = $_ENV['APP_ENV'] ?? 'dev';
        if (!in_array($currentEnv, $this->enabledEnvironments)) {
            return false;
        }

        // Apply sampling rate
        if ($this->sampleRate < 1.0) {
            return mt_rand() / mt_getrandmax() <= $this->sampleRate;
        }

        return true;
    }

    public function beginTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        if ($this->isEnabled()) {
            $this->delegate->beginTransaction($name, $type, $timestamp);
        }
    }

    public function beginCurrentTransaction(string $name, string $type, ?float $timestamp = null): void
    {
        if ($this->isEnabled()) {
            $this->delegate->beginCurrentTransaction($name, $type, $timestamp);
        }
    }

    public function endCurrentTransaction(?string $result = null, ?string $outcome = null): void
    {
        if ($this->isEnabled()) {
            $this->delegate->endCurrentTransaction($result, $outcome);
        }
    }

    public function setTransactionContext(array $context): void
    {
        if ($this->isEnabled()) {
            $this->delegate->setTransactionContext($context);
        }
    }

    public function setTransactionLabels(array $labels): void
    {
        if ($this->isEnabled()) {
            $this->delegate->setTransactionLabels($labels);
        }
    }

    public function beginCurrentSpan(string $name, string $type, ?string $subType = null, ?string $action = null): void
    {
        if ($this->isEnabled()) {
            $this->delegate->beginCurrentSpan($name, $type, $subType, $action);
        }
    }

    public function endCurrentSpan(): void
    {
        if ($this->isEnabled()) {
            $this->delegate->endCurrentSpan();
        }
    }

    public function captureCurrentSpan(string $name, string $type, callable $callback, ?string $subType = null, ?string $action = null): mixed
    {
        if ($this->isEnabled()) {
            return $this->delegate->captureCurrentSpan($name, $type, $callback, $subType, $action);
        }
        return $callback();
    }

    public function captureException(\Throwable $exception): void
    {
        if ($this->isEnabled()) {
            $this->delegate->captureException($exception);
        }
    }

    public function captureError(string $type, string $message, ?string $file = null, ?int $line = null): void
    {
        if ($this->isEnabled()) {
            $this->delegate->captureError($type, $message, $file, $line);
        }
    }

    public function setUserContext(?string $id = null, ?string $email = null, ?string $username = null): void
    {
        if ($this->isEnabled()) {
            $this->delegate->setUserContext($id, $email, $username);
        }
    }

    public function setCustomContext(array $context): void
    {
        if ($this->isEnabled()) {
            $this->delegate->setCustomContext($context);
        }
    }

    public function addTransactionLabel(string $key, $value): void
    {
        if ($this->isEnabled()) {
            $this->delegate->addTransactionLabel($key, $value);
        }
    }

    public function getCurrentTraceId(): ?string
    {
        if ($this->isEnabled()) {
            return $this->delegate->getCurrentTraceId();
        }
        return null;
    }

    public function getCurrentTransactionId(): ?string
    {
        if ($this->isEnabled()) {
            return $this->delegate->getCurrentTransactionId();
        }
        return null;
    }

    public function startRequestTransaction(Request $request): void
    {
        if ($this->isEnabled()) {
            $this->delegate->startRequestTransaction($request);
        }
    }

    public function endRequestTransaction(Response $response): void
    {
        if ($this->isEnabled()) {
            $this->delegate->endRequestTransaction($response);
        }
    }
}