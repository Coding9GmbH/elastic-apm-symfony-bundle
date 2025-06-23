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

use Coding9\ElasticApmBundle\Interactor\BlackholeInteractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BlackholeInteractorTest extends TestCase
{
    private BlackholeInteractor $interactor;

    protected function setUp(): void
    {
        $this->interactor = new BlackholeInteractor();
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->assertFalse($this->interactor->isEnabled());
    }

    public function testStartTransactionReturnsTransaction(): void
    {
        $transaction = $this->interactor->startTransaction('test', 'request');
        
        $this->assertInstanceOf(\Coding9\ElasticApmBundle\Model\Transaction::class, $transaction);
        $this->assertEquals('test', $transaction->getName());
        $this->assertEquals('request', $transaction->getType());
    }

    public function testCaptureCurrentSpanExecutesCallback(): void
    {
        $callbackExecuted = false;
        
        $result = $this->interactor->captureCurrentSpan(
            'test span',
            'test',
            function() use (&$callbackExecuted) {
                $callbackExecuted = true;
                return 'callback result';
            }
        );
        
        $this->assertTrue($callbackExecuted);
        $this->assertEquals('callback result', $result);
    }

    public function testCaptureExceptionDoesNothing(): void
    {
        $exception = new \Exception('Test exception');
        
        $this->interactor->captureException($exception);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function testGetTraceContextReturnsEmptyArray(): void
    {
        $this->assertEquals([], $this->interactor->getTraceContext());
    }

    public function testGetCurrentTransactionReturnsNull(): void
    {
        $this->assertNull($this->interactor->getCurrentTransaction());
    }

    public function testStopTransactionDoesNothing(): void
    {
        $transaction = $this->interactor->startTransaction('test', 'request');
        
        $this->interactor->stopTransaction($transaction, 200);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function testStartSpanReturnsSpan(): void
    {
        $transaction = $this->interactor->startTransaction('test', 'request');
        $span = $this->interactor->startSpan('test_span', 'db', 'mysql', $transaction);
        
        $this->assertInstanceOf(\Coding9\ElasticApmBundle\Model\Span::class, $span);
        $this->assertEquals('test_span', $span->getName());
        $this->assertEquals('db', $span->getType());
        $this->assertEquals('mysql', $span->getSubtype());
    }

    public function testStopSpanDoesNothing(): void
    {
        $span = $this->interactor->startSpan('test_span', 'db');
        
        $this->interactor->stopSpan($span);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function testIsRecordingReturnsFalse(): void
    {
        $this->assertFalse($this->interactor->isRecording());
    }
}