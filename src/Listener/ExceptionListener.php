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

        // Add request context
        $request = $event->getRequest();
        $this->interactor->setCustomContext([
            'request' => [
                'url' => $request->getUri(),
                'method' => $request->getMethod(),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
                'body' => $this->getRequestBody($request),
            ],
            'response' => [
                'status_code' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500,
            ],
            'user' => [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ],
        ]);

        $this->interactor->captureException($exception);
    }
    
    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'];
        $sanitized = [];
        
        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);
            if (!in_array($lowerKey, $sensitive, true)) {
                $sanitized[$key] = $values;
            } else {
                $sanitized[$key] = ['[REDACTED]'];
            }
        }
        
        return $sanitized;
    }
    
    private function getRequestBody($request): ?string
    {
        $content = $request->getContent();
        
        if (empty($content)) {
            return null;
        }
        
        // Limit body size
        if (strlen($content) > 10000) {
            return substr($content, 0, 10000) . '... (truncated)';
        }
        
        return $content;
    }
}