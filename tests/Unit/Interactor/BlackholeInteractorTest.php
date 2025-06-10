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

    public function testBeginTransactionDoesNothing(): void
    {
        $this->interactor->beginTransaction('test', 'request');
        
        // Should not throw any exceptions
        $this->assertTrue(true);
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

    public function testGetCurrentTraceIdReturnsNull(): void
    {
        $this->assertNull($this->interactor->getCurrentTraceId());
    }

    public function testStartRequestTransactionDoesNothing(): void
    {
        $request = Request::create('/test');
        
        $this->interactor->startRequestTransaction($request);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function testEndRequestTransactionDoesNothing(): void
    {
        $response = new Response('test', 200);
        
        $this->interactor->endRequestTransaction($response);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }
}