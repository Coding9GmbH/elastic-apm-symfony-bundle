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


namespace ElasticApmBundle\Twig;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ElasticApmExtension extends AbstractExtension
{
    private ElasticApmInteractorInterface $interactor;
    private array $rumConfig;

    public function __construct(ElasticApmInteractorInterface $interactor, array $rumConfig)
    {
        $this->interactor = $interactor;
        $this->rumConfig = $rumConfig;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('apm_rum_config', [$this, 'getRumConfig']),
            new TwigFunction('apm_trace_id', [$this, 'getTraceId']),
            new TwigFunction('apm_transaction_id', [$this, 'getTransactionId']),
        ];
    }

    public function getRumConfig(): array
    {
        if (!$this->rumConfig['rum']['enabled']) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'serviceName' => $this->rumConfig['rum']['service_name'],
            'serverUrl' => $this->rumConfig['rum']['server_url'],
            'serviceVersion' => $this->rumConfig['service']['version'],
            'environment' => $this->rumConfig['service']['environment'],
            'pageLoadTraceId' => $this->interactor->getCurrentTraceId(),
            'pageLoadTransactionId' => $this->interactor->getCurrentTransactionId(),
        ];
    }

    public function getTraceId(): ?string
    {
        return $this->interactor->getCurrentTraceId();
    }

    public function getTransactionId(): ?string
    {
        return $this->interactor->getCurrentTransactionId();
    }
}