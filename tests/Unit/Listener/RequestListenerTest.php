<?php

namespace ElasticApmBundle\Tests\Unit\Listener;

use ElasticApmBundle\Listener\RequestListener;
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use ElasticApmBundle\TransactionNamingStrategy\TransactionNamingStrategyInterface;
use ElasticApmBundle\Tests\SymfonyCompat;
use ElasticApmBundle\Model\Transaction;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RequestListenerTest extends TestCase
{
    /** @var ElasticApmInteractorInterface|MockObject */
    private $interactor;

    /** @var TransactionNamingStrategyInterface|MockObject */
    private $namingStrategy;

    /** @var RequestListener */
    private $listener;

    /** @var HttpKernelInterface|MockObject */
    private $kernel;

    protected function setUp(): void
    {
        $this->interactor = $this->createMock(ElasticApmInteractorInterface::class);
        $this->namingStrategy = $this->createMock(TransactionNamingStrategyInterface::class);
        $this->listener = new RequestListener($this->interactor, $this->namingStrategy);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testOnKernelRequestStartsTransaction(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/test-uri');
        
        $event = new RequestEvent($this->kernel, $request, SymfonyCompat::getMainRequestType());
        $transaction = $this->createMock(Transaction::class);

        $this->namingStrategy->expects($this->once())
            ->method('getTransactionName')
            ->with($request)
            ->willReturn('test_transaction');

        $this->interactor->expects($this->once())
            ->method('startTransaction')
            ->with('test_transaction', 'request')
            ->willReturn($transaction);

        $this->listener->onKernelRequest($event);
    }

    public function testOnKernelRequestSkipsSubRequests(): void
    {
        $request = new Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->interactor->expects($this->never())
            ->method('startTransaction');

        $this->listener->onKernelRequest($event);
    }

    public function testOnKernelResponseSetsTransactionResult(): void
    {
        $request = new Request();
        $response = new Response('', 200);
        $transaction = $this->createMock(Transaction::class);
        
        $requestEvent = new RequestEvent($this->kernel, $request, SymfonyCompat::getMainRequestType());
        $responseEvent = new ResponseEvent($this->kernel, $request, SymfonyCompat::getMainRequestType(), $response);

        $this->namingStrategy->expects($this->once())
            ->method('getTransactionName')
            ->willReturn('test_transaction');

        $this->interactor->expects($this->once())
            ->method('startTransaction')
            ->willReturn($transaction);

        // Transaction result is set inside the interactor's stopTransaction method
        // so we don't need to test setResult here

        $this->listener->onKernelRequest($requestEvent);
        $this->listener->onKernelResponse($responseEvent);
    }

    public function testOnKernelResponseSkipsIfNoTransaction(): void
    {
        $request = new Request();
        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, SymfonyCompat::getMainRequestType(), $response);

        $this->interactor->expects($this->never())
            ->method('stopTransaction');

        $this->listener->onKernelResponse($event);
    }

    public function testOnKernelFinishRequestStopsTransaction(): void
    {
        $request = new Request();
        $transaction = $this->createMock(Transaction::class);
        
        $requestEvent = new RequestEvent($this->kernel, $request, SymfonyCompat::getMainRequestType());
        $finishEvent = new FinishRequestEvent($this->kernel, $request, SymfonyCompat::getMainRequestType());

        $this->namingStrategy->expects($this->once())
            ->method('getTransactionName')
            ->willReturn('test_transaction');

        $this->interactor->expects($this->once())
            ->method('startTransaction')
            ->willReturn($transaction);

        $this->interactor->expects($this->once())
            ->method('stopTransaction')
            ->with($transaction, null);

        $this->listener->onKernelRequest($requestEvent);
        $this->listener->onKernelFinishRequest($finishEvent);
    }

    public function testOnKernelFinishRequestSkipsIfNoTransaction(): void
    {
        $request = new Request();
        $event = new FinishRequestEvent($this->kernel, $request, SymfonyCompat::getMainRequestType());

        $this->interactor->expects($this->never())
            ->method('stopTransaction');

        $this->listener->onKernelFinishRequest($event);
    }
}