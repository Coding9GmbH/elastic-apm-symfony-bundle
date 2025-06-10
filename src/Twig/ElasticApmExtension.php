<?php

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