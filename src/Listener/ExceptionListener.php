<?php

namespace ElasticApmBundle\Listener;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionListener implements EventSubscriberInterface
{
    private ElasticApmInteractorInterface $interactor;
    private array $ignoredExceptions;
    private bool $unwrapExceptions;

    public function __construct(
        ElasticApmInteractorInterface $interactor,
        array $ignoredExceptions = [],
        bool $unwrapExceptions = false
    ) {
        $this->interactor = $interactor;
        $this->ignoredExceptions = $ignoredExceptions;
        $this->unwrapExceptions = $unwrapExceptions;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Check if exception should be ignored
        foreach ($this->ignoredExceptions as $ignoredClass) {
            if ($exception instanceof $ignoredClass) {
                return;
            }
        }

        // Unwrap exception if needed
        if ($this->unwrapExceptions && method_exists($exception, 'getPrevious')) {
            $previous = $exception->getPrevious();
            if ($previous !== null) {
                $exception = $previous;
            }
        }

        $this->interactor->captureException($exception);
    }
}