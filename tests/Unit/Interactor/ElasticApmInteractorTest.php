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

use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractor;
use Coding9\ElasticApmBundle\Model\Transaction;
use Coding9\ElasticApmBundle\Model\Span;
use Coding9\ElasticApmBundle\Model\Error;
use Coding9\ElasticApmBundle\Client\ApmClient;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ElasticApmInteractorTest extends TestCase
{
    /** @var ElasticApmInteractor */
    private $interactor;
    
    /** @var array */
    private $config;

    protected function setUp(): void
    {
        $this->config = [
            'enabled' => true,
            'server' => [
                'url' => 'http://localhost:8200',
                'secret_token' => 'test_token',
                'api_key' => null,
            ],
            'transactions' => [
                'sample_rate' => 1.0,
            ],
        ];
        
        $this->interactor = new ElasticApmInteractor($this->config);
    }

    public function testStartTransaction(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals('test_transaction', $transaction->getName());
        $this->assertEquals('request', $transaction->getType());
        $this->assertSame($transaction, $this->interactor->getCurrentTransaction());
    }

    public function testStopTransaction(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $this->interactor->stopTransaction($transaction, 200);

        $this->assertEquals('HTTP 2xx', $transaction->getResult());
        $this->assertNull($this->interactor->getCurrentTransaction());
    }

    public function testStartSpan(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $span = $this->interactor->startSpan('test_span', 'db', 'mysql', $transaction);

        $this->assertInstanceOf(Span::class, $span);
        $this->assertEquals('test_span', $span->getName());
        $this->assertEquals('db', $span->getType());
        $this->assertEquals('mysql', $span->getSubtype());
    }

    public function testStopSpan(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        $span = $this->interactor->startSpan('test_span', 'db');
        
        // This should not throw any exceptions
        $this->interactor->stopSpan($span);
        
        $this->assertTrue(true);
    }

    public function testCaptureException(): void
    {
        $exception = new \Exception('Test exception');
        
        // This should not throw any exceptions
        $this->interactor->captureException($exception);
        
        $this->assertTrue(true);
    }
    
    public function testCaptureError(): void
    {
        // This should not throw any exceptions
        $this->interactor->captureError('Test error', ['context' => 'value']);
        
        $this->assertTrue(true);
    }

    public function testSetTransactionCustomData(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $this->interactor->setTransactionCustomData($transaction, ['custom' => 'data']);

        $this->assertEquals(['custom' => 'data'], $transaction->getCustomContext());
    }

    public function testSetSpanCustomData(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        $span = $this->interactor->startSpan('test_span', 'db');
        
        $this->interactor->setSpanCustomData($span, ['custom' => 'data']);

        $this->assertEquals(['custom' => 'data'], $span->getContext());
    }
    
    public function testSetUserContext(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $this->interactor->setUserContext(['id' => '123', 'username' => 'test']);
        
        $this->assertEquals(['id' => '123', 'username' => 'test'], $transaction->getUserContext());
    }
    
    public function testSetCustomContext(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $this->interactor->setCustomContext(['key' => 'value']);
        
        $this->assertEquals(['key' => 'value'], $transaction->getCustomContext());
    }
    
    public function testSetLabels(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $this->interactor->setLabels(['environment' => 'test']);
        
        $this->assertEquals(['environment' => 'test'], $transaction->getLabels());
    }

    public function testFlush(): void
    {
        // This should not throw any exceptions
        $this->interactor->flush();
        
        $this->assertTrue(true);
    }
    
    public function testIsEnabled(): void
    {
        $this->assertTrue($this->interactor->isEnabled());
    }
    
    public function testIsRecording(): void
    {
        $this->assertTrue($this->interactor->isRecording());
    }
    
    public function testGetTraceContext(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $context = $this->interactor->getTraceContext();
        
        $this->assertArrayHasKey('traceparent', $context);
        $this->assertArrayHasKey('tracestate', $context);
        $this->assertStringContainsString($transaction->getTraceId(), $context['traceparent']);
        $this->assertStringContainsString($transaction->getId(), $context['traceparent']);
    }
    
    public function testCaptureCurrentSpan(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $result = $this->interactor->captureCurrentSpan(
            'test_span',
            'custom',
            function() {
                return 'callback result';
            },
            ['context' => 'value']
        );
        
        $this->assertEquals('callback result', $result);
    }
    
    public function testCaptureCurrentSpanWithException(): void
    {
        $transaction = $this->interactor->startTransaction('test_transaction', 'request');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');
        
        $this->interactor->captureCurrentSpan(
            'test_span',
            'custom',
            function() {
                throw new \Exception('Test exception');
            }
        );
    }
    
    public function testDisabledInteractor(): void
    {
        $config = $this->config;
        $config['enabled'] = false;
        
        $disabledInteractor = new ElasticApmInteractor($config);
        
        $this->assertFalse($disabledInteractor->isEnabled());
        $this->assertFalse($disabledInteractor->isRecording());
        
        $transaction = $disabledInteractor->startTransaction('test', 'request');
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertNull($disabledInteractor->getCurrentTransaction());
    }
}