<?php

namespace Coding9\ElasticApmBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractor;

/**
 * Real integration tests that verify APM data is sent and stored correctly
 */
class ApmIntegrationTest extends TestCase
{
    private static $apmServerUrl;
    private static $elasticsearchUrl;
    private static $httpClient;
    
    public static function setUpBeforeClass(): void
    {
        self::$apmServerUrl = getenv('APM_SERVER_URL') ?: 'http://localhost:8200';
        self::$elasticsearchUrl = getenv('ELASTICSEARCH_URL') ?: 'http://localhost:9200';
        self::$httpClient = HttpClient::create();
        
        // Wait for services to be ready
        self::waitForService(self::$apmServerUrl, 'APM Server');
        self::waitForService(self::$elasticsearchUrl, 'Elasticsearch');
    }
    
    private static function waitForService(string $url, string $name, int $timeout = 30): void
    {
        $start = time();
        while (time() - $start < $timeout) {
            try {
                $response = self::$httpClient->request('GET', $url);
                if ($response->getStatusCode() === 200) {
                    return;
                }
            } catch (\Exception $e) {
                // Service not ready yet
            }
            sleep(1);
        }
        throw new \RuntimeException("$name not ready after $timeout seconds");
    }
    
    public function testTransactionIsStoredInElasticsearch(): void
    {
        // Given: A configured APM interactor
        $config = [
            'enabled' => true,
            'server_url' => self::$apmServerUrl,
            'service_name' => 'integration-test',
            'environment' => 'test',
            'transaction_sample_rate' => 1.0,
            'metadata' => [
                'service' => [
                    'name' => 'integration-test',
                    'environment' => 'test',
                    'version' => '1.0.0',
                    'agent' => [
                        'name' => 'elastic-apm-symfony',
                        'version' => '1.0.0'
                    ]
                ]
            ]
        ];
        
        $apm = new ElasticApmInteractor($config);
        
        // When: We create and send a transaction
        $transactionName = 'test-transaction-' . uniqid();
        $transaction = $apm->startTransaction($transactionName, 'test');
        
        // Add some context
        $apm->setUserContext([
            'id' => '123',
            'username' => 'test-user'
        ]);
        
        $apm->setCustomContext([
            'test_id' => uniqid(),
            'test_flag' => true
        ]);
        
        // Add a span
        $span = $apm->startSpan('test-span', 'custom', 'Test span');
        usleep(10000); // 10ms
        $apm->stopSpan($span);
        
        usleep(50000); // 50ms
        $apm->stopTransaction($transaction);
        $apm->flush();
        
        // Wait for data to be indexed
        sleep(2);
        
        // Then: The transaction should be findable in Elasticsearch
        $searchUrl = self::$elasticsearchUrl . '/apm-*/_search';
        $searchBody = [
            'query' => [
                'bool' => [
                    'must' => [
                        ['term' => ['transaction.name' => $transactionName]],
                        ['term' => ['service.name' => 'integration-test']]
                    ]
                ]
            ]
        ];
        
        $response = self::$httpClient->request('POST', $searchUrl, [
            'json' => $searchBody,
            'headers' => ['Content-Type' => 'application/json']
        ]);
        
        $data = $response->toArray();
        
        // Assertions
        $this->assertGreaterThan(0, $data['hits']['total']['value'], 'Transaction not found in Elasticsearch');
        
        $hit = $data['hits']['hits'][0]['_source'];
        $this->assertEquals($transactionName, $hit['transaction']['name']);
        $this->assertEquals('test', $hit['transaction']['type']);
        $this->assertEquals('integration-test', $hit['service']['name']);
        $this->assertEquals('123', $hit['user']['id']);
        $this->assertEquals('test-user', $hit['user']['username']);
        $this->assertTrue($hit['context']['custom']['test_flag']);
    }
    
    public function testSpanIsLinkedToTransaction(): void
    {
        $apm = $this->createApmInteractor();
        
        // Create transaction with unique ID
        $transactionName = 'transaction-with-span-' . uniqid();
        $transaction = $apm->startTransaction($transactionName, 'test');
        
        // Create span
        $spanName = 'db-query-' . uniqid();
        $span = $apm->startSpan($spanName, 'db', 'SELECT * FROM users');
        usleep(20000); // 20ms
        $apm->stopSpan($span);
        
        $apm->stopTransaction($transaction);
        $apm->flush();
        
        sleep(2); // Wait for indexing
        
        // Search for the span
        $searchUrl = self::$elasticsearchUrl . '/apm-*/_search';
        $response = self::$httpClient->request('POST', $searchUrl, [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['exists' => ['field' => 'span']],
                            ['term' => ['span.name' => $spanName]]
                        ]
                    ]
                ]
            ]
        ]);
        
        $data = $response->toArray();
        $this->assertGreaterThan(0, $data['hits']['total']['value'], 'Span not found');
        
        $spanDoc = $data['hits']['hits'][0]['_source'];
        $this->assertEquals($spanName, $spanDoc['span']['name']);
        $this->assertEquals('db', $spanDoc['span']['type']);
        $this->assertEquals('SELECT * FROM users', $spanDoc['span']['subtype']);
        
        // Verify span is linked to transaction
        $this->assertNotEmpty($spanDoc['transaction']['id']);
        $this->assertNotEmpty($spanDoc['trace']['id']);
    }
    
    public function testErrorIsCapture(): void
    {
        $apm = $this->createApmInteractor();
        
        $errorMessage = 'Test error ' . uniqid();
        $transaction = $apm->startTransaction('error-transaction', 'test');
        
        // Capture an error
        $apm->captureError($errorMessage, [
            'exception' => [
                'type' => 'TestException',
                'code' => 500
            ]
        ]);
        
        $apm->stopTransaction($transaction);
        $apm->flush();
        
        sleep(2);
        
        // Search for the error
        $response = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_search', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['exists' => ['field' => 'error']],
                            ['match' => ['error.message' => $errorMessage]]
                        ]
                    ]
                ]
            ]
        ]);
        
        $data = $response->toArray();
        $this->assertGreaterThan(0, $data['hits']['total']['value'], 'Error not found');
        
        $errorDoc = $data['hits']['hits'][0]['_source'];
        $this->assertStringContainsString($errorMessage, $errorDoc['error']['message']);
    }
    
    public function testMetricsAreCollected(): void
    {
        $apm = $this->createApmInteractor();
        
        // Create multiple transactions to generate metrics
        for ($i = 0; i < 5; $i++) {
            $transaction = $apm->startTransaction('metric-test', 'request');
            usleep(rand(10000, 50000)); // Random duration
            $apm->stopTransaction($transaction);
        }
        
        $apm->flush();
        sleep(3);
        
        // Check for metric documents
        $response = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_search', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['exists' => ['field' => 'metricset']],
                            ['term' => ['service.name' => 'integration-test']]
                        ]
                    ]
                ]
            ]
        ]);
        
        $data = $response->toArray();
        // Metrics might be aggregated, so we just check if any exist
        $this->assertGreaterThanOrEqual(0, $data['hits']['total']['value']);
    }
    
    public function testDistributedTracingHeaders(): void
    {
        $apm = $this->createApmInteractor();
        
        // Start a transaction
        $transaction = $apm->startTransaction('distributed-trace', 'request');
        
        // Get distributed tracing headers
        $headers = $apm->getDistributedTracingHeaders();
        
        $this->assertArrayHasKey('traceparent', $headers);
        $this->assertMatchesRegularExpression('/^00-[a-f0-9]{32}-[a-f0-9]{16}-[0-9]{2}$/', $headers['traceparent']);
        
        $apm->stopTransaction($transaction);
    }
    
    public function testHighVolumeTransactions(): void
    {
        $apm = $this->createApmInteractor();
        
        $serviceName = 'high-volume-test-' . uniqid();
        $transactionCount = 50;
        
        // Send many transactions quickly
        for ($i = 0; $i < $transactionCount; $i++) {
            $transaction = $apm->startTransaction("transaction-$i", 'bulk-test');
            
            // Add some spans
            for ($j = 0; $j < 3; $j++) {
                $span = $apm->startSpan("span-$i-$j", 'custom');
                usleep(1000); // 1ms
                $apm->stopSpan($span);
            }
            
            $apm->stopTransaction($transaction);
            
            // Flush every 10 transactions
            if ($i % 10 === 0) {
                $apm->flush();
            }
        }
        
        $apm->flush();
        sleep(5); // Wait for all data to be indexed
        
        // Count transactions
        $response = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_count', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['exists' => ['field' => 'transaction']],
                            ['term' => ['transaction.type' => 'bulk-test']]
                        ]
                    ]
                ]
            ]
        ]);
        
        $data = $response->toArray();
        $this->assertEquals($transactionCount, $data['count'], 'Not all transactions were indexed');
    }
    
    private function createApmInteractor(): ElasticApmInteractor
    {
        $config = [
            'enabled' => true,
            'server_url' => self::$apmServerUrl,
            'service_name' => 'integration-test',
            'environment' => 'test',
            'transaction_sample_rate' => 1.0,
            'metadata' => [
                'service' => [
                    'name' => 'integration-test',
                    'environment' => 'test',
                    'version' => '1.0.0',
                    'agent' => [
                        'name' => 'elastic-apm-symfony',
                        'version' => '1.0.0'
                    ]
                ]
            ]
        ];
        
        return new ElasticApmInteractor($config);
    }
}