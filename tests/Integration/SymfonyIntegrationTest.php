<?php

namespace Coding9\ElasticApmBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Tests the bundle integration with a real Symfony application
 */
class SymfonyIntegrationTest extends TestCase
{
    private static $symfonyUrl;
    private static $elasticsearchUrl;
    private static $httpClient;
    
    public static function setUpBeforeClass(): void
    {
        self::$symfonyUrl = getenv('SYMFONY_APP_URL') ?: 'http://localhost:8080';
        self::$elasticsearchUrl = getenv('ELASTICSEARCH_URL') ?: 'http://localhost:9200';
        self::$httpClient = HttpClient::create();
        
        // Wait for Symfony app to be ready
        self::waitForSymfonyApp();
    }
    
    private static function waitForSymfonyApp(int $timeout = 30): void
    {
        $start = time();
        while (time() - $start < $timeout) {
            try {
                $response = self::$httpClient->request('GET', self::$symfonyUrl);
                if ($response->getStatusCode() === 200) {
                    return;
                }
            } catch (\Exception $e) {
                // App not ready yet
            }
            sleep(1);
        }
        throw new \RuntimeException("Symfony app not ready after $timeout seconds");
    }
    
    public function testControllerRequestCreatesTransaction(): void
    {
        // Make a request to the test controller
        $response = self::$httpClient->request('GET', self::$symfonyUrl . '/api/users');
        $this->assertEquals(200, $response->getStatusCode());
        
        // Wait for APM data to be indexed
        sleep(2);
        
        // Search for the transaction
        $searchResponse = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_search', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['transaction.name' => 'GET /api/users']],
                            ['range' => ['@timestamp' => ['gte' => 'now-1m']]]
                        ]
                    ]
                ],
                'sort' => [['@timestamp' => 'desc']]
            ]
        ]);
        
        $data = $searchResponse->toArray();
        $this->assertGreaterThan(0, $data['hits']['total']['value'], 'Transaction not found');
        
        $transaction = $data['hits']['hits'][0]['_source'];
        $this->assertEquals('GET /api/users', $transaction['transaction']['name']);
        $this->assertEquals('request', $transaction['transaction']['type']);
        $this->assertEquals('200', $transaction['transaction']['result']);
        $this->assertGreaterThan(0, $transaction['transaction']['duration']['us']);
    }
    
    public function testSlowRequestHasCorrectDuration(): void
    {
        $startTime = microtime(true);
        
        // Make a slow request
        $response = self::$httpClient->request('GET', self::$symfonyUrl . '/api/slow');
        $this->assertEquals(200, $response->getStatusCode());
        
        $duration = microtime(true) - $startTime;
        $this->assertGreaterThan(2, $duration, 'Request should take at least 2 seconds');
        
        sleep(2);
        
        // Find the transaction
        $searchResponse = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_search', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['transaction.name' => 'GET /api/slow']],
                            ['range' => ['@timestamp' => ['gte' => 'now-1m']]]
                        ]
                    ]
                ],
                'sort' => [['@timestamp' => 'desc']]
            ]
        ]);
        
        $data = $searchResponse->toArray();
        $this->assertGreaterThan(0, $data['hits']['total']['value']);
        
        $transaction = $data['hits']['hits'][0]['_source'];
        $durationMs = $transaction['transaction']['duration']['us'] / 1000;
        $this->assertGreaterThan(2000, $durationMs, 'Transaction duration should be > 2000ms');
    }
    
    public function testExceptionIsCaptured(): void
    {
        // Make a request that throws an exception
        try {
            self::$httpClient->request('GET', self::$symfonyUrl . '/api/error');
        } catch (\Exception $e) {
            // Expected
        }
        
        sleep(2);
        
        // Search for the error
        $searchResponse = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_search', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['exists' => ['field' => 'error']],
                            ['match' => ['error.exception.message' => 'Test exception for APM']],
                            ['range' => ['@timestamp' => ['gte' => 'now-1m']]]
                        ]
                    ]
                ]
            ]
        ]);
        
        $data = $searchResponse->toArray();
        $this->assertGreaterThan(0, $data['hits']['total']['value'], 'Error not found');
        
        $error = $data['hits']['hits'][0]['_source'];
        $this->assertEquals('Test exception for APM', $error['error']['exception'][0]['message']);
        $this->assertEquals('Exception', $error['error']['exception'][0]['type']);
    }
    
    public function testDifferentHttpStatusCodes(): void
    {
        $statuses = [];
        
        // Make multiple requests to get different status codes
        for ($i = 0; $i < 10; $i++) {
            try {
                $response = self::$httpClient->request('GET', self::$symfonyUrl . '/api/random-status');
                $statuses[] = $response->getStatusCode();
            } catch (\Exception $e) {
                // Client errors are expected
                if ($e->getCode() >= 400 && $e->getCode() < 600) {
                    $statuses[] = $e->getCode();
                }
            }
        }
        
        sleep(3);
        
        // Verify transactions with different status codes exist
        $searchResponse = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_search', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['transaction.name' => 'GET /api/random-status']],
                            ['range' => ['@timestamp' => ['gte' => 'now-1m']]]
                        ]
                    ]
                ],
                'size' => 20,
                'sort' => [['@timestamp' => 'desc']]
            ]
        ]);
        
        $data = $searchResponse->toArray();
        $this->assertGreaterThan(0, $data['hits']['total']['value']);
        
        // Check that we have different status codes
        $foundStatuses = [];
        foreach ($data['hits']['hits'] as $hit) {
            $foundStatuses[] = $hit['_source']['transaction']['result'];
        }
        
        $uniqueStatuses = array_unique($foundStatuses);
        $this->assertGreaterThan(1, count($uniqueStatuses), 'Should have multiple different status codes');
    }
    
    public function testRequestHeadersAreCaptured(): void
    {
        // Make request with custom headers
        $customHeaders = [
            'X-Test-Header' => 'test-value',
            'X-Request-ID' => uniqid(),
            'User-Agent' => 'APM-Integration-Test'
        ];
        
        $response = self::$httpClient->request('GET', self::$symfonyUrl . '/', [
            'headers' => $customHeaders
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        sleep(2);
        
        // Find the transaction
        $searchResponse = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_search', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['exists' => ['field' => 'transaction']],
                            ['match' => ['user_agent.original' => 'APM-Integration-Test']],
                            ['range' => ['@timestamp' => ['gte' => 'now-1m']]]
                        ]
                    ]
                ],
                'sort' => [['@timestamp' => 'desc']]
            ]
        ]);
        
        $data = $searchResponse->toArray();
        $this->assertGreaterThan(0, $data['hits']['total']['value'], 'Transaction with custom headers not found');
    }
    
    public function testConcurrentRequests(): void
    {
        $concurrentRequests = 10;
        $requestId = uniqid('concurrent-');
        
        // Create multiple async requests
        $responses = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = self::$httpClient->request('GET', self::$symfonyUrl . '/', [
                'headers' => ['X-Test-ID' => $requestId . '-' . $i]
            ]);
        }
        
        // Wait for all responses
        foreach ($responses as $response) {
            $this->assertEquals(200, $response->getStatusCode());
        }
        
        sleep(3);
        
        // Count transactions
        $searchResponse = self::$httpClient->request('POST', self::$elasticsearchUrl . '/apm-*/_count', [
            'json' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['exists' => ['field' => 'transaction']],
                            ['prefix' => ['http.request.headers.x-test-id' => $requestId]],
                            ['range' => ['@timestamp' => ['gte' => 'now-1m']]]
                        ]
                    ]
                ]
            ]
        ]);
        
        $data = $searchResponse->toArray();
        $this->assertEquals($concurrentRequests, $data['count'], 'Not all concurrent requests were tracked');
    }
    
    /**
     * @group performance
     */
    public function testApmOverheadIsAcceptable(): void
    {
        $iterations = 20;
        $durations = [];
        
        // Warm up
        self::$httpClient->request('GET', self::$symfonyUrl . '/');
        
        // Measure request times
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $response = self::$httpClient->request('GET', self::$symfonyUrl . '/');
            $response->getContent(); // Force request completion
            $durations[] = (microtime(true) - $start) * 1000; // Convert to ms
        }
        
        // Calculate statistics
        $avgDuration = array_sum($durations) / count($durations);
        $maxDuration = max($durations);
        
        // Assert performance is acceptable
        $this->assertLessThan(100, $avgDuration, 'Average request time should be < 100ms');
        $this->assertLessThan(200, $maxDuration, 'Max request time should be < 200ms');
        
        // Log performance stats
        echo sprintf(
            "\nPerformance: avg=%.2fms, max=%.2fms, min=%.2fms\n",
            $avgDuration,
            $maxDuration,
            min($durations)
        );
    }
}