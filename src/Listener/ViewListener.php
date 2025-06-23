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


namespace Coding9\ElasticApmBundle\Listener;

use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ViewListener implements EventSubscriberInterface
{
    private ElasticApmInteractorInterface $interactor;
    private array $viewSpans = [];

    public function __construct(ElasticApmInteractorInterface $interactor)
    {
        $this->interactor = $interactor;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['onKernelViewPre', 100],
                ['onKernelViewPost', -100],
            ],
        ];
    }

    public function onKernelViewPre(ViewEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $span = $this->interactor->startSpan(
            'view',
            'template',
            'render'
        );
        
        $this->viewSpans[spl_object_id($event->getRequest())] = $span;
    }

    public function onKernelViewPost(ViewEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = spl_object_id($event->getRequest());
        
        if (isset($this->viewSpans[$requestId])) {
            $this->interactor->stopSpan($this->viewSpans[$requestId]);
            unset($this->viewSpans[$requestId]);
        }
    }
}