#!/usr/bin/env php
<?php

// Simple test to send data directly to APM Server
$metadata = [
    "metadata" => [
        "service" => [
            "name" => "simple-test",
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
        "name" => "TEST " . date('Y-m-d H:i:s'),
        "type" => "request",
        "duration" => rand(50, 500),
        "timestamp" => (int)(microtime(true) * 1000000),
        "result" => "HTTP 2xx",
        "sampled" => true,
        "span_count" => [
            "started" => 0,
            "dropped" => 0
        ]
    ]
];

$payload = json_encode($metadata) . "\n" . json_encode($transaction) . "\n";

echo "Sending transaction: " . $transaction['transaction']['name'] . "\n";

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
curl_close($ch);

echo "Response: HTTP $httpCode\n";
if ($httpCode !== 202) {
    echo "Error: $response\n";
}

// Wait and check
sleep(2);
$ch = curl_init('http://localhost:9200/apm-*-transaction-*/_count');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
echo "Total transactions in Elasticsearch: " . ($data['count'] ?? 'unknown') . "\n";