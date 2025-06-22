#!/usr/bin/env php
<?php

echo "Testing Message Queue APM Integration\n";
echo "====================================\n\n";

// 1. Send a message via the API
echo "1. Sending message via API...\n";
$ch = curl_init("http://localhost:8888/api/send-message");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(["data" => "Test from script"]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Response (HTTP $httpCode): $response\n";

// 2. Wait for processing
echo "\n2. Waiting for data to be indexed...\n";
sleep(5);

// 3. Check APM data
echo "\n3. Checking APM data in Elasticsearch...\n";

// Get last 30 seconds of data
$query = [
    "query" => [
        "bool" => [
            "must" => [
                ["term" => ["service.name" => "symfony-test-app"]],
                ["range" => ["@timestamp" => ["gte" => "now-30s"]]]
            ]
        ]
    ],
    "size" => 20,
    "sort" => [["@timestamp" => "desc"]],
    "_source" => ["@timestamp", "transaction.name", "span.name", "processor.event", "transaction.type"]
];

$ch = curl_init("http://localhost:9200/apm-*/_search");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode($query),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$total = $data['hits']['total']['value'] ?? 0;

echo "   Found $total documents in last 30 seconds\n";

if ($total > 0) {
    echo "\n   Recent APM events:\n";
    foreach ($data['hits']['hits'] as $hit) {
        $source = $hit['_source'];
        $timestamp = $source['@timestamp'];
        $event = $source['processor']['event'];
        
        if ($event === 'transaction') {
            echo "   - Transaction: " . $source['transaction']['name'] . " (type: " . $source['transaction']['type'] . ") at $timestamp\n";
        } elseif ($event === 'span') {
            echo "   - Span: " . $source['span']['name'] . " at $timestamp\n";
        }
    }
}

// 4. Check total documents for service
echo "\n4. Total documents for symfony-test-app:\n";
$ch = curl_init("http://localhost:9200/apm-*/_count?q=service.name:symfony-test-app");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
echo "   Total: " . ($data['count'] ?? 0) . " documents\n";

echo "\nDone!\n";