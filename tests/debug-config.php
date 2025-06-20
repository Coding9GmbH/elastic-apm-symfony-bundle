#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractor;
use Coding9\ElasticApmBundle\Client\ApmClient;

echo "Debugging APM Configuration\n";
echo "===========================\n\n";

// Test script configuration
$testConfig = [
    'enabled' => true,
    'server_url' => getenv('ELASTIC_APM_SERVER_URL') ?: 'http://apm-server:8200',
    'service_name' => 'test-script',
    'environment' => 'test',
    'transaction_sample_rate' => 1.0,
];

echo "1. Test script configuration:\n";
print_r($testConfig);

// Create ApmClient with proper configuration
$properConfig = [
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

echo "\n2. Proper configuration format:\n";
print_r($properConfig);

// Test with ApmClient directly
echo "\n3. Testing with ApmClient directly...\n";
$client = new ApmClient($properConfig);

// Use reflection to check if client is configured properly
$reflection = new ReflectionClass($client);
$configProp = $reflection->getProperty('config');
$configProp->setAccessible(true);
$actualConfig = $configProp->getValue($client);

echo "   ApmClient actual config:\n";
echo "   - enabled: " . ($actualConfig['enabled'] ?? 'not set') . "\n";
echo "   - server.url: " . ($actualConfig['server']['url'] ?? 'not set') . "\n";
echo "   - service.name: " . ($actualConfig['service']['name'] ?? 'not set') . "\n";

// Check metadata
$metadataProp = $reflection->getProperty('metadata');
$metadataProp->setAccessible(true);
$metadata = $metadataProp->getValue($client);

echo "\n4. Generated metadata:\n";
echo json_encode($metadata, JSON_PRETTY_PRINT) . "\n";

echo "\nDone!\n";