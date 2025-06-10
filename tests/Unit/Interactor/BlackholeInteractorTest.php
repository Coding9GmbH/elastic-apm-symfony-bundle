<?php

namespace ElasticApmBundle\Tests\Unit\Interactor;

use ElasticApmBundle\Interactor\BlackholeInteractor;
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
        
        $this->assertInstanceOf(\ElasticApmBundle\Model\Transaction::class, $transaction);
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
        
        $this->assertInstanceOf(\ElasticApmBundle\Model\Span::class, $span);
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