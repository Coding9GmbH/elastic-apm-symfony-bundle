<?php

namespace ElasticApmBundle\Tests\Unit\Interactor;

use ElasticApmBundle\Interactor\AdaptiveInteractor;
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Nipwaayoni\Events\Transaction;
use Nipwaayoni\Events\Span;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AdaptiveInteractorTest extends TestCase
{
    /** @var ElasticApmInteractorInterface|MockObject */
    private $fallbackInteractor;

    /** @var ElasticApmInteractorInterface|MockObject */
    private $primaryInteractor;

    /** @var AdaptiveInteractor */
    private $adaptiveInteractor;

    protected function setUp(): void
    {
        $this->fallbackInteractor = $this->createMock(ElasticApmInteractorInterface::class);
        $this->primaryInteractor = $this->createMock(ElasticApmInteractorInterface::class);
        $this->adaptiveInteractor = new AdaptiveInteractor($this->fallbackInteractor);
    }

    public function testSetInteractorSwitchesToPrimary(): void
    {
        $transaction = $this->createMock(Transaction::class);
        
        $this->fallbackInteractor->expects($this->never())
            ->method('startTransaction');
        
        $this->primaryInteractor->expects($this->once())
            ->method('startTransaction')
            ->with('test', 'request')
            ->willReturn($transaction);

        $this->adaptiveInteractor->setInteractor($this->primaryInteractor);
        $result = $this->adaptiveInteractor->startTransaction('test', 'request');

        $this->assertSame($transaction, $result);
    }

    public function testStartTransactionUsesFallbackByDefault(): void
    {
        $transaction = $this->createMock(Transaction::class);
        
        $this->fallbackInteractor->expects($this->once())
            ->method('startTransaction')
            ->with('test', 'request')
            ->willReturn($transaction);

        $result = $this->adaptiveInteractor->startTransaction('test', 'request');

        $this->assertSame($transaction, $result);
    }

    public function testStopTransactionDelegatesToCurrentInteractor(): void
    {
        $transaction = $this->createMock(Transaction::class);
        
        $this->fallbackInteractor->expects($this->once())
            ->method('stopTransaction')
            ->with($transaction, 200);

        $this->adaptiveInteractor->stopTransaction($transaction, 200);
    }

    public function testStartSpanDelegatesToCurrentInteractor(): void
    {
        $span = $this->createMock(Span::class);
        $transaction = $this->createMock(Transaction::class);
        
        $this->fallbackInteractor->expects($this->once())
            ->method('startSpan')
            ->with('test_span', 'db', 'mysql', $transaction)
            ->willReturn($span);

        $result = $this->adaptiveInteractor->startSpan('test_span', 'db', 'mysql', $transaction);

        $this->assertSame($span, $result);
    }

    public function testStopSpanDelegatesToCurrentInteractor(): void
    {
        $span = $this->createMock(Span::class);
        
        $this->fallbackInteractor->expects($this->once())
            ->method('stopSpan')
            ->with($span);

        $this->adaptiveInteractor->stopSpan($span);
    }

    public function testCaptureExceptionDelegatesToCurrentInteractor(): void
    {
        $exception = new \Exception('Test exception');
        
        $this->fallbackInteractor->expects($this->once())
            ->method('captureException')
            ->with($exception);

        $this->adaptiveInteractor->captureException($exception);
    }

    public function testSetTransactionCustomDataDelegatesToCurrentInteractor(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $customData = ['key' => 'value'];
        
        $this->fallbackInteractor->expects($this->once())
            ->method('setTransactionCustomData')
            ->with($transaction, $customData);

        $this->adaptiveInteractor->setTransactionCustomData($transaction, $customData);
    }

    public function testSetSpanCustomDataDelegatesToCurrentInteractor(): void
    {
        $span = $this->createMock(Span::class);
        $customData = ['key' => 'value'];
        
        $this->fallbackInteractor->expects($this->once())
            ->method('setSpanCustomData')
            ->with($span, $customData);

        $this->adaptiveInteractor->setSpanCustomData($span, $customData);
    }

    public function testFlushDelegatesToCurrentInteractor(): void
    {
        $this->fallbackInteractor->expects($this->once())
            ->method('flush');

        $this->adaptiveInteractor->flush();
    }
}