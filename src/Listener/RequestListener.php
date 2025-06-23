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
use Coding9\ElasticApmBundle\TransactionNamingStrategy\TransactionNamingStrategyInterface;
use Coding9\ElasticApmBundle\Model\Transaction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestListener implements EventSubscriberInterface
{
    private ElasticApmInteractorInterface $interactor;
    private TransactionNamingStrategyInterface $namingStrategy;
    private array $transactions = [];
    private array $controllerSpans = [];

    public function __construct(
        ElasticApmInteractorInterface $interactor,
        TransactionNamingStrategyInterface $namingStrategy
    ) {
        $this->interactor = $interactor;
        $this->namingStrategy = $namingStrategy;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
            KernelEvents::CONTROLLER => ['onKernelController', 128],
            KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', -2048],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', -2048],
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $transactionName = $this->namingStrategy->getTransactionName($request);
        
        $transaction = $this->interactor->startTransaction($transactionName, 'request');
        
        // Store transaction for later
        $this->transactions[spl_object_id($request)] = $transaction;
        
        // Add request context
        $this->interactor->setCustomContext([
            'request' => [
                'method' => $request->getMethod(),
                'url' => $request->getUri(),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
            ]
        ]);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = spl_object_id($request);
        
        if (!isset($this->transactions[$requestId])) {
            return;
        }

        // Start controller span
        $controller = $event->getController();
        $controllerName = $this->getControllerName($controller);
        
        $span = $this->interactor->startSpan(
            'controller',
            'app',
            'controller',
            $this->transactions[$requestId]
        );
        
        $this->controllerSpans[$requestId] = $span;
        
        // Update transaction name if we have better information
        if ($controllerName && $controllerName !== 'unknown') {
            $this->transactions[$requestId]->setName($controllerName);
        }
        
        // Add controller metadata
        $this->interactor->setCustomContext([
            'controller' => [
                'class' => is_array($controller) ? get_class($controller[0]) : get_class($controller),
                'method' => is_array($controller) ? $controller[1] : '__invoke',
                'route' => $request->attributes->get('_route', 'unknown'),
                'route_params' => $request->attributes->get('_route_params', []),
            ]
        ]);
    }
    
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = spl_object_id($event->getRequest());
        
        // Stop controller span
        if (isset($this->controllerSpans[$requestId])) {
            $this->interactor->stopSpan($this->controllerSpans[$requestId]);
            unset($this->controllerSpans[$requestId]);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $requestId = spl_object_id($request);
        
        if (isset($this->transactions[$requestId])) {
            $transaction = $this->transactions[$requestId];
            $transaction->setResult($this->getResultString($response->getStatusCode()));
        }
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        $request = $event->getRequest();
        $requestId = spl_object_id($request);
        
        if (isset($this->transactions[$requestId])) {
            $transaction = $this->transactions[$requestId];
            
            // Stop any remaining controller spans
            if (isset($this->controllerSpans[$requestId])) {
                $this->interactor->stopSpan($this->controllerSpans[$requestId]);
                unset($this->controllerSpans[$requestId]);
            }
            
            $this->interactor->stopTransaction($transaction);
            unset($this->transactions[$requestId]);
        }
    }
    
    public function onKernelTerminate(): void
    {
        // Flush any pending APM data after response is sent
        $this->interactor->flush();
    }
    
    private function getResultString(int $statusCode): string
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return 'HTTP 2xx';
        } elseif ($statusCode >= 300 && $statusCode < 400) {
            return 'HTTP 3xx';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return 'HTTP 4xx';
        } elseif ($statusCode >= 500) {
            return 'HTTP 5xx';
        }
        
        return 'HTTP ' . $statusCode;
    }
    
    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'set-cookie', 'x-api-key'];
        $sanitized = [];
        
        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);
            if (!in_array($lowerKey, $sensitive, true)) {
                $sanitized[$key] = $values;
            }
        }
        
        return $sanitized;
    }
    
    private function getControllerName($controller): string
    {
        if (is_array($controller)) {
            return sprintf('%s::%s', get_class($controller[0]), $controller[1]);
        }
        
        if (is_object($controller)) {
            if (method_exists($controller, '__invoke')) {
                return get_class($controller) . '::__invoke';
            }
            return get_class($controller);
        }
        
        if (is_string($controller)) {
            return $controller;
        }
        
        return 'unknown';
    }
}