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


namespace Coding9\ElasticApmBundle\Tests\Unit\Listener;

use Coding9\ElasticApmBundle\Listener\ExceptionListener;
use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Coding9\ElasticApmBundle\Tests\SymfonyCompat;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExceptionListenerTest extends TestCase
{
    /** @var ElasticApmInteractorInterface|MockObject */
    private $interactor;

    /** @var ExceptionListener */
    private $listener;

    /** @var HttpKernelInterface|MockObject */
    private $kernel;

    protected function setUp(): void
    {
        $this->interactor = $this->createMock(ElasticApmInteractorInterface::class);
        $this->listener = new ExceptionListener($this->interactor);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testOnKernelExceptionCapturesException(): void
    {
        $exception = new \Exception('Test exception');
        $request = new Request();
        $event = new ExceptionEvent($this->kernel, $request, SymfonyCompat::getMainRequestType(), $exception);

        $this->interactor->expects($this->once())
            ->method('captureException')
            ->with($exception);

        $this->listener->onKernelException($event);
    }

    public function testOnKernelExceptionHandlesThrowable(): void
    {
        $throwable = new \Error('Test error');
        $request = new Request();
        $event = new ExceptionEvent($this->kernel, $request, SymfonyCompat::getMainRequestType(), $throwable);

        $this->interactor->expects($this->once())
            ->method('captureException')
            ->with($throwable);

        $this->listener->onKernelException($event);
    }
}