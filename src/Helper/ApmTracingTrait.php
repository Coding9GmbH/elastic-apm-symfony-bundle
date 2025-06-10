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