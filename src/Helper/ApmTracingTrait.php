<?php

namespace ElasticApmBundle\Helper;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;

trait ApmTracingTrait
{
    protected ?ElasticApmInteractorInterface $apmInteractor = null;

    public function setApmInteractor(ElasticApmInteractorInterface $interactor): void
    {
        $this->apmInteractor = $interactor;
    }

    protected function apmSpan(string $name, string $type, callable $callback, ?string $subType = null, ?string $action = null): mixed
    {
        if ($this->apmInteractor === null) {
            return $callback();
        }

        return $this->apmInteractor->captureCurrentSpan($name, $type, $callback, $subType, $action);
    }

    protected function apmBeginSpan(string $name, string $type, ?string $subType = null, ?string $action = null): void
    {
        if ($this->apmInteractor !== null) {
            $this->apmInteractor->beginCurrentSpan($name, $type, $subType, $action);
        }
    }

    protected function apmEndSpan(): void
    {
        if ($this->apmInteractor !== null) {
            $this->apmInteractor->endCurrentSpan();
        }
    }

    protected function apmSetLabel(string $key, $value): void
    {
        if ($this->apmInteractor !== null) {
            $this->apmInteractor->addTransactionLabel($key, $value);
        }
    }

    protected function apmSetCustomContext(array $context): void
    {
        if ($this->apmInteractor !== null) {
            $this->apmInteractor->setCustomContext($context);
        }
    }
}