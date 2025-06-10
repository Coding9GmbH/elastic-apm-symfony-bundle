<?php

namespace ElasticApmBundle\Listener;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use ElasticApmBundle\TransactionNamingStrategy\TransactionNamingStrategyInterface;
use ElasticApmBundle\Model\Transaction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestListener implements EventSubscriberInterface
{
    private ElasticApmInteractorInterface $interactor;
    private TransactionNamingStrategyInterface $namingStrategy;
    private array $transactions = [];

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
            KernelEvents::RESPONSE => ['onKernelResponse', -2048],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', -2048],
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
            $this->interactor->stopTransaction($transaction);
            unset($this->transactions[$requestId]);
        }
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
}