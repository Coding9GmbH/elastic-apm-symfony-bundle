#!/usr/bin/env php
<?php

echo "Testing Direct APM Server Communication\n";
echo "======================================\n\n";

// Test 1: Check APM Server is accessible
echo "1. Testing APM Server connectivity...\n";
$ch = curl_init('http://localhost:8200/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Status: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   APM Server version: " . ($data['version'] ?? 'unknown') . "\n";
    echo "   ✓ APM Server is accessible\n";
} else {
    echo "   ✗ APM Server is not accessible\n";
    exit(1);
}

// Test 2: Send minimal valid APM data
echo "\n2. Sending minimal valid APM data...\n";

$metadata = [
    "metadata" => [
        "service" => [
            "name" => "test-service",
            "environment" => "test",
            "version" => "1.0.0",
            "agent" => [
                "name" => "test-agent",
                "version" => "1.0.0"
            ],
            "language" => [
                "name" => "php",
                "version" => PHP_VERSION
            ]
        ]
    ]
];

$transaction = [
    "transaction" => [
        "id" => bin2hex(random_bytes(8)),
        "trace_id" => bin2hex(random_bytes(16)),
        "name" => "GET /test",
        "type" => "request",
        "duration" => 123.45,
        "timestamp" => (int)(microtime(true) * 1000000),
        "result" => "HTTP 2xx",
        "sampled" => true,
        "span_count" => [
            "started" => 0,
            "dropped" => 0
        ]
    ]
];

// Build NDJSON payload
$payload = json_encode($metadata) . "\n" . json_encode($transaction) . "\n";

echo "   Payload size: " . strlen($payload) . " bytes\n";

// Send to APM Server
$ch = curl_init('http://localhost:8200/intake/v2/events');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-ndjson',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP Status: $httpCode\n";
if ($error) {
    echo "   Error: $error\n";
} elseif ($httpCode === 202) {
    echo "   ✓ Data accepted by APM Server\n";
} else {
    echo "   Response: $response\n";
}

// Test 3: Check if data arrived in Elasticsearch
echo "\n3. Checking Elasticsearch for our data...\n";
sleep(2); // Wait for indexing

$ch = curl_init('http://localhost:9200/apm-*/_search?q=service.name:test-service&size=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

$hits = $data['hits']['total']['value'] ?? 0;
echo "   Found $hits documents for test-service\n";

if ($hits > 0) {
    echo "   ✓ Data successfully stored in Elasticsearch\n";
    echo "\nYou should now see 'test-service' in Kibana at:\n";
    echo "http://localhost:5601 > Observability > APM\n";
} else {
    echo "   ✗ Data not found in Elasticsearch\n";
}

echo "\nDone!\n";