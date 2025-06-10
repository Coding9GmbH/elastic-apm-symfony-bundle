<?php

namespace ElasticApmBundle\Tests\Unit\Interactor;

use ElasticApmBundle\Interactor\ElasticApmInteractor;
use Nipwaayoni\Agent;
use Nipwaayoni\Events\Transaction;
use Nipwaayoni\Events\Span;
use Nipwaayoni\Events\Error;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ElasticApmInteractorTest extends TestCase
{
    /** @var Agent|MockObject */
    private $agent;

    /** @var ElasticApmInteractor */
    private $interactor;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(Agent::class);
        $this->interactor = new ElasticApmInteractor($this->agent);
    }

    public function testStartTransaction(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $this->agent->expects($this->once())
            ->method('startTransaction')
            ->with('test_transaction', 'request')
            ->willReturn($transaction);

        $result = $this->interactor->startTransaction('test_transaction', 'request');

        $this->assertSame($transaction, $result);
    }

    public function testStopTransaction(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method('stop')
            ->willReturn($transaction);

        $this->interactor->stopTransaction($transaction, 200);

        $transaction->expects($this->once())
            ->method('setResult')
            ->with('HTTP 2xx');
    }

    public function testStartSpan(): void
    {
        $span = $this->createMock(Span::class);
        $transaction = $this->createMock(Transaction::class);
        
        $this->agent->expects($this->once())
            ->method('factory')
            ->willReturn($this->agent);
        
        $this->agent->expects($this->once())
            ->method('newSpan')
            ->with('test_span', $transaction)
            ->willReturn($span);

        $span->expects($this->once())
            ->method('start')
            ->willReturn($span);

        $result = $this->interactor->startSpan('test_span', 'db', null, $transaction);

        $this->assertSame($span, $result);
    }

    public function testStopSpan(): void
    {
        $span = $this->createMock(Span::class);
        $span->expects($this->once())
            ->method('stop');

        $this->interactor->stopSpan($span);
    }

    public function testCaptureException(): void
    {
        $exception = new \Exception('Test exception');
        $error = $this->createMock(Error::class);

        $this->agent->expects($this->once())
            ->method('factory')
            ->willReturn($this->agent);

        $this->agent->expects($this->once())
            ->method('newError')
            ->with($exception)
            ->willReturn($error);

        $this->agent->expects($this->once())
            ->method('putEvent')
            ->with($error);

        $this->interactor->captureException($exception);
    }

    public function testSetTransactionCustomData(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method('setMeta')
            ->with(['custom' => 'data']);

        $this->interactor->setTransactionCustomData($transaction, ['custom' => 'data']);
    }

    public function testSetSpanCustomData(): void
    {
        $span = $this->createMock(Span::class);
        $span->expects($this->once())
            ->method('setMeta')
            ->with(['custom' => 'data']);

        $this->interactor->setSpanCustomData($span, ['custom' => 'data']);
    }

    public function testFlush(): void
    {
        $this->agent->expects($this->once())
            ->method('send');

        $this->interactor->flush();
    }
}