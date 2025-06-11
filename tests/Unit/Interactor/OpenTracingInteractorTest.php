<?php

namespace ElasticApmBundle\Tests\Unit\Interactor;

use ElasticApmBundle\Interactor\OpenTracingInteractor;
use ElasticApmBundle\Model\Span;
use ElasticApmBundle\Model\Transaction;
use ElasticApmBundle\TransactionNamingStrategy\TransactionNamingStrategyInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OpenTracingInteractorTest extends TestCase
{
    private OpenTracingInteractor $interactor;
    private array $config;
    private TransactionNamingStrategyInterface $namingStrategy;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->config = [
            'enabled' => true,
            'server' => [
                'url' => 'http://localhost:14268',
                'api_key' => 'test-api-key',
                'secret_token' => 'test-secret-token',
            ],
            'service' => [
                'name' => 'test-service',
                'version' => '1.0.0',
                'environment' => 'test',
            ],
        ];

        $this->namingStrategy = $this->createMock(TransactionNamingStrategyInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->interactor = new OpenTracingInteractor(
            $this->config,
            $this->namingStrategy,
            $this->logger
        );
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->interactor->isEnabled());

        $disabledInteractor = new OpenTracingInteractor(
            ['enabled' => false],
            $this->namingStrategy,
            $this->logger
        );
        $this->assertFalse($disabledInteractor->isEnabled());
    }

    public function testIsRecording(): void
    {
        $this->assertFalse($this->interactor->isRecording());

        $this->interactor->startTransaction('test', 'request');
        $this->assertTrue($this->interactor->isRecording());
    }

    public function testStartAndStopTransaction(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals('test-transaction', $transaction->getName());
        $this->assertEquals('request', $transaction->getType());
        $this->assertNotNull($this->interactor->getCurrentTransaction());

        $this->interactor->stopTransaction($transaction, 200);
        $this->assertNull($this->interactor->getCurrentTransaction());
    }

    public function testStartAndStopSpan(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');
        $span = $this->interactor->startSpan('test-span', 'db', 'mysql', $transaction);

        $this->assertInstanceOf(Span::class, $span);
        $this->assertEquals('test-span', $span->getName());
        $this->assertEquals('db', $span->getType());
        $this->assertEquals('mysql', $span->getSubtype());

        $this->interactor->stopSpan($span);
        // Span should be removed from stack
    }

    public function testCaptureCurrentSpan(): void
    {
        $this->interactor->startTransaction('test-transaction', 'request');

        $result = $this->interactor->captureCurrentSpan('test-span', 'custom', function () {
            return 'test-result';
        }, ['subtype' => 'test-subtype']);

        $this->assertEquals('test-result', $result);
    }

    public function testCaptureCurrentSpanWithException(): void
    {
        $this->interactor->startTransaction('test-transaction', 'request');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->interactor->captureCurrentSpan('test-span', 'custom', function () {
            throw new \RuntimeException('Test exception');
        });
    }

    public function testCaptureException(): void
    {
        $exception = new \RuntimeException('Test exception');
        
        // Should not throw
        $this->interactor->captureException($exception);
        $this->assertTrue(true);
    }

    public function testCaptureError(): void
    {
        $this->interactor->captureError('Test error message', [
            'type' => 'E_USER_ERROR',
            'file' => '/path/to/file.php',
            'line' => 123,
        ]);

        $this->assertTrue(true); // Should not throw
    }

    public function testSetUserContext(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');

        $this->interactor->setUserContext([
            'id' => '123',
            'email' => 'test@example.com',
            'username' => 'testuser',
        ]);

        $userContext = $transaction->getUserContext();
        $this->assertEquals('123', $userContext['id']);
        $this->assertEquals('test@example.com', $userContext['email']);
        $this->assertEquals('testuser', $userContext['username']);
    }

    public function testSetCustomContext(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');

        $this->interactor->setCustomContext([
            'order_id' => '12345',
            'customer_type' => 'premium',
        ]);

        $customContext = $transaction->getCustomContext();
        $this->assertEquals('12345', $customContext['order_id']);
        $this->assertEquals('premium', $customContext['customer_type']);
    }

    public function testSetTransactionLabels(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');

        $this->interactor->setTransactionLabels([
            'environment' => 'production',
            'region' => 'eu-west-1',
        ]);

        $labels = $transaction->getLabels();
        $this->assertEquals('production', $labels['environment']);
        $this->assertEquals('eu-west-1', $labels['region']);
    }

    public function testAddTransactionLabel(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');

        $this->interactor->addTransactionLabel('key1', 'value1');
        $this->interactor->addTransactionLabel('key2', 'value2');

        $labels = $transaction->getLabels();
        $this->assertEquals('value1', $labels['key1']);
        $this->assertEquals('value2', $labels['key2']);
    }

    public function testSetTransactionCustomData(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');

        $this->interactor->setTransactionCustomData($transaction, [
            'custom_field' => 'custom_value',
            'another_field' => 123,
        ]);

        $customContext = $transaction->getCustomContext();
        $this->assertEquals('custom_value', $customContext['custom_field']);
        $this->assertEquals(123, $customContext['another_field']);
    }

    public function testSetSpanCustomData(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');
        $span = $this->interactor->startSpan('test-span', 'db');

        $this->interactor->setSpanCustomData($span, [
            'query' => 'SELECT * FROM users',
            'row_count' => 42,
        ]);

        $context = $span->getContext();
        $this->assertEquals('SELECT * FROM users', $context['query']);
        $this->assertEquals(42, $context['row_count']);
    }

    public function testGetCurrentTraceId(): void
    {
        $this->assertNull($this->interactor->getCurrentTraceId());

        $transaction = $this->interactor->startTransaction('test-transaction', 'request');
        $traceId = $this->interactor->getCurrentTraceId();

        $this->assertNotNull($traceId);
        $this->assertEquals($transaction->getTraceId(), $traceId);
    }

    public function testGetCurrentTransactionId(): void
    {
        $this->assertNull($this->interactor->getCurrentTransactionId());

        $transaction = $this->interactor->startTransaction('test-transaction', 'request');
        $transactionId = $this->interactor->getCurrentTransactionId();

        $this->assertNotNull($transactionId);
        $this->assertEquals($transaction->getId(), $transactionId);
    }

    public function testGetCurrentTransaction(): void
    {
        $this->assertNull($this->interactor->getCurrentTransaction());

        $transaction = $this->interactor->startTransaction('test-transaction', 'request');
        $currentTransaction = $this->interactor->getCurrentTransaction();

        $this->assertSame($transaction, $currentTransaction);
    }

    public function testGetTraceContext(): void
    {
        $context = $this->interactor->getTraceContext();
        $this->assertEmpty($context);

        $transaction = $this->interactor->startTransaction('test-transaction', 'request');
        $context = $this->interactor->getTraceContext();

        $this->assertArrayHasKey('trace_id', $context);
        $this->assertArrayHasKey('transaction_id', $context);
        $this->assertArrayHasKey('parent_span_id', $context);
        $this->assertEquals($transaction->getTraceId(), $context['trace_id']);
        $this->assertEquals($transaction->getId(), $context['transaction_id']);
        $this->assertNull($context['parent_span_id']);

        $span = $this->interactor->startSpan('test-span', 'db');
        $context = $this->interactor->getTraceContext();
        $this->assertEquals($span->getId(), $context['parent_span_id']);
    }

    public function testStartAndEndRequestTransaction(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test_route');
        $request->server->set('REQUEST_METHOD', 'GET');
        $request->server->set('REQUEST_URI', '/test');

        $this->namingStrategy->expects($this->once())
            ->method('getTransactionName')
            ->with($request)
            ->willReturn('GET test_route');

        $this->interactor->startRequestTransaction($request);
        $this->assertNotNull($this->interactor->getCurrentTransaction());

        $response = new Response('', 200);
        $this->interactor->endRequestTransaction($response);
        $this->assertNull($this->interactor->getCurrentTransaction());
    }

    public function testDistributedTracingWithW3CHeader(): void
    {
        $request = new Request();
        $request->headers->set('traceparent', '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');

        $this->namingStrategy->expects($this->once())
            ->method('getTransactionName')
            ->willReturn('test');

        $this->interactor->startRequestTransaction($request);
        $transaction = $this->interactor->getCurrentTransaction();

        $this->assertEquals('4bf92f3577b34da6a3ce929d0e0e4736', $transaction->getTraceId());
    }

    public function testDistributedTracingWithJaegerHeader(): void
    {
        $request = new Request();
        $request->headers->set('uber-trace-id', '4bf92f3577b34da6a3ce929d0e0e4736:00f067aa0ba902b7:0:1');

        $this->namingStrategy->expects($this->once())
            ->method('getTransactionName')
            ->willReturn('test');

        $this->interactor->startRequestTransaction($request);
        $transaction = $this->interactor->getCurrentTransaction();

        $this->assertEquals('4bf92f3577b34da6a3ce929d0e0e4736', $transaction->getTraceId());
    }

    public function testDistributedTracingWithB3Headers(): void
    {
        $request = new Request();
        $request->headers->set('x-b3-traceid', '4bf92f3577b34da6a3ce929d0e0e4736');
        $request->headers->set('x-b3-spanid', '00f067aa0ba902b7');

        $this->namingStrategy->expects($this->once())
            ->method('getTransactionName')
            ->willReturn('test');

        $this->interactor->startRequestTransaction($request);
        $transaction = $this->interactor->getCurrentTransaction();

        $this->assertEquals('4bf92f3577b34da6a3ce929d0e0e4736', $transaction->getTraceId());
    }

    public function testFlush(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('[OpenTracing] Flush called (no-op for OpenTracing)');

        $this->interactor->flush();
    }

    public function testBeginAndEndCurrentTransaction(): void
    {
        $this->interactor->beginCurrentTransaction('test', 'request');
        $this->assertNotNull($this->interactor->getCurrentTransaction());

        $this->interactor->endCurrentTransaction('200', 'success');
        $this->assertNull($this->interactor->getCurrentTransaction());
    }

    public function testBeginAndEndCurrentSpan(): void
    {
        $this->interactor->startTransaction('test-transaction', 'request');
        
        $this->interactor->beginCurrentSpan('test-span', 'db', 'mysql', 'query');
        // We can't directly test the span stack, but we can verify it works with endCurrentSpan
        
        $this->interactor->endCurrentSpan();
        // Should not throw
        $this->assertTrue(true);
    }

    public function testSetLabels(): void
    {
        $transaction = $this->interactor->startTransaction('test-transaction', 'request');

        $this->interactor->setLabels([
            'label1' => 'value1',
            'label2' => 'value2',
        ]);

        $labels = $transaction->getLabels();
        $this->assertEquals('value1', $labels['label1']);
        $this->assertEquals('value2', $labels['label2']);
    }

    public function testDisabledInteractor(): void
    {
        $disabledInteractor = new OpenTracingInteractor(
            ['enabled' => false],
            $this->namingStrategy,
            $this->logger
        );

        // All methods should work but not actually do anything
        $transaction = $disabledInteractor->startTransaction('test', 'request');
        $this->assertInstanceOf(Transaction::class, $transaction);

        $span = $disabledInteractor->startSpan('test', 'db');
        $this->assertInstanceOf(Span::class, $span);

        $result = $disabledInteractor->captureCurrentSpan('test', 'custom', fn() => 'result');
        $this->assertEquals('result', $result);

        // These should not throw
        $disabledInteractor->captureException(new \Exception('test'));
        $disabledInteractor->captureError('test error');
        $disabledInteractor->flush();
    }
}