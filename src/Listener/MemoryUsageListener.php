<?php

namespace ElasticApmBundle\Listener;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class MemoryUsageListener implements EventSubscriberInterface
{
    private ElasticApmInteractorInterface $interactor;
    private string $memoryUsageLabel;

    public function __construct(
        ElasticApmInteractorInterface $interactor,
        string $memoryUsageLabel = 'memory_usage'
    ) {
        $this->interactor = $interactor;
        $this->memoryUsageLabel = $memoryUsageLabel;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        $this->interactor->addTransactionLabel($this->memoryUsageLabel, $memoryUsage);
        $this->interactor->addTransactionLabel($this->memoryUsageLabel . '_peak', $memoryPeak);
    }
}