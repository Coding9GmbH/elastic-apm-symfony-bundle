<?php

namespace ElasticApmBundle\Tests\Unit\Listener;

use ElasticApmBundle\Listener\ExceptionListener;
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
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
        $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $exception);

        $this->interactor->expects($this->once())
            ->method('captureException')
            ->with($exception);

        $this->listener->onKernelException($event);
    }

    public function testOnKernelExceptionHandlesThrowable(): void
    {
        $throwable = new \Error('Test error');
        $request = new Request();
        $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $throwable);

        $this->interactor->expects($this->once())
            ->method('captureException')
            ->with($throwable);

        $this->listener->onKernelException($event);
    }
}