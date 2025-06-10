<?php

namespace ElasticApmBundle\Listener;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CommandListener implements EventSubscriberInterface
{
    private ElasticApmInteractorInterface $interactor;

    public function __construct(ElasticApmInteractorInterface $interactor)
    {
        $this->interactor = $interactor;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 2048],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -2048],
            ConsoleEvents::ERROR => ['onConsoleError', 0],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$command) {
            return;
        }

        $name = sprintf('console %s', $command->getName());
        $this->interactor->beginCurrentTransaction($name, 'cli');

        // Set command context
        $this->interactor->setTransactionContext([
            'cli' => [
                'command' => $command->getName(),
                'arguments' => $event->getInput()->getArguments(),
                'options' => $event->getInput()->getOptions(),
            ],
        ]);
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $exitCode = $event->getExitCode();
        $result = $exitCode === 0 ? 'success' : sprintf('exit:%d', $exitCode);
        $outcome = $exitCode === 0 ? 'success' : 'failure';

        $this->interactor->endCurrentTransaction($result, $outcome);
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->interactor->captureException($event->getError());
    }
}