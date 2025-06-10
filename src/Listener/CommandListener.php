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