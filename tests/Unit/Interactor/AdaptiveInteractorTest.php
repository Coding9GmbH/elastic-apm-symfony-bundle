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


namespace Coding9\ElasticApmBundle\Tests\Unit\Interactor;

use Coding9\ElasticApmBundle\Interactor\AdaptiveInteractor;
use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Coding9\ElasticApmBundle\Model\Transaction;
use Coding9\ElasticApmBundle\Model\Span;
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