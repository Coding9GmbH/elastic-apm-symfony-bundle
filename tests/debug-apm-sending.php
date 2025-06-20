#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Coding9\ElasticApmBundle\Client\ApmClient;
use Coding9\ElasticApmBundle\Model\Transaction;

echo "Debugging APM Data Sending\n";
echo "==========================\n\n";

// Configuration
$config = [
    'enabled' => true,
    'server' => [
        'url' => 'http://apm-server:8200',
    ],
    'service' => [
        'name' => 'debug-test',
        'version' => '1.0.0',
        'environment' => 'test'
    ],
    'transactions' => [
        'sample_rate' => 1.0,
    ]
];

// Create APM client with debug logger
$logger = new class extends \Psr\Log\AbstractLogger {
    public function log($level, $message, array $context = []): void
    {
        echo "[" . strtoupper($level) . "] $message\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context) . "\n";
        }
    }
};

$apmClient = new ApmClient($config, $logger);

// Create and send a test transaction
echo "\n1. Creating test transaction...\n";
$transaction = new Transaction();
$transaction->setName('TEST /debug')
    ->setType('request')
    ->setTimestamp(microtime(true) * 1000000)
    ->setDuration(100.0)
    ->setResult('success');

echo "   Transaction ID: " . $transaction->getId() . "\n";
echo "   Transaction Name: " . $transaction->getName() . "\n";

echo "\n2. Sending transaction...\n";
$apmClient->sendTransaction($transaction);

echo "\n3. Flushing queue...\n";
$apmClient->flush();

echo "\n4. Testing direct APM server connection...\n";
$ch = curl_init('http://localhost:8200/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   APM Server response (HTTP $httpCode): " . substr($response, 0, 100) . "...\n";

echo "\n5. Checking if data format is correct...\n";
// Use reflection to access private methods for debugging
$reflection = new ReflectionClass($apmClient);
$method = $reflection->getMethod('formatNdjson');
$method->setAccessible(true);

// Create a simple queue for testing
$testQueue = [
    ['metadata' => ['service' => ['name' => 'test']]],
    ['transaction' => $transaction->toArray()]
];

$ndjson = $method->invoke($apmClient, $testQueue);
echo "   NDJSON output:\n";
$lines = explode("\n", trim($ndjson));
foreach ($lines as $i => $line) {
    echo "   Line $i: " . substr($line, 0, 100) . (strlen($line) > 100 ? "..." : "") . "\n";
}

echo "\nDone!\n";