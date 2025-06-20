#!/usr/bin/env php
<?php

echo "Testing Full APM Flow\n";
echo "====================\n\n";

// 1. Check services
echo "1. Checking services are running...\n";
$services = [
    'Elasticsearch' => 'http://localhost:9200/',
    'APM Server' => 'http://localhost:8200/',
    'Kibana' => 'http://localhost:5601/api/status',
    'Symfony App' => 'http://localhost:8888/',
];

foreach ($services as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   $name: " . ($code === 200 ? "✓ OK" : "✗ Failed (HTTP $code)") . "\n";
}

// 2. Send test transaction
echo "\n2. Sending test transaction...\n";
$metadata = [
    "metadata" => [
        "service" => [
            "name" => "flow-test",
            "environment" => "test",
            "version" => "1.0.0",
            "agent" => ["name" => "test-agent", "version" => "1.0.0"],
            "language" => ["name" => "php", "version" => PHP_VERSION]
        ]
    ]
];

$transaction = [
    "transaction" => [
        "id" => bin2hex(random_bytes(8)),
        "trace_id" => bin2hex(random_bytes(16)),
        "name" => "TEST /flow-check",
        "type" => "request",
        "duration" => 150,
        "timestamp" => (int)(microtime(true) * 1000000),
        "result" => "success",
        "sampled" => true,
        "span_count" => ["started" => 0, "dropped" => 0]
    ]
];

$payload = json_encode($metadata) . "\n" . json_encode($transaction) . "\n";

$ch = curl_init('http://localhost:8200/intake/v2/events');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-ndjson'],
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   APM Server response: " . ($code === 202 ? "✓ Accepted" : "✗ Failed (HTTP $code)") . "\n";

// 3. Wait and verify in Elasticsearch
echo "\n3. Verifying data in Elasticsearch...\n";
sleep(3);

$ch = curl_init('http://localhost:9200/apm-*/_search?q=service.name:flow-test');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
$count = $data['hits']['total']['value'] ?? 0;
curl_close($ch);

echo "   Documents found: $count\n";

// 4. Check what services are in APM
echo "\n4. Services with APM data:\n";
$ch = curl_init('http://localhost:9200/apm-*/_search');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode([
        "size" => 0,
        "aggs" => [
            "services" => [
                "terms" => ["field" => "service.name", "size" => 20]
            ]
        ]
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

if (isset($data['aggregations']['services']['buckets'])) {
    foreach ($data['aggregations']['services']['buckets'] as $bucket) {
        echo "   - " . $bucket['key'] . " (" . $bucket['doc_count'] . " documents)\n";
    }
}

echo "\n5. Instructions to view in Kibana:\n";
echo "   1. Open http://localhost:5601\n";
echo "   2. Go to the menu (☰) → Observability → APM\n";
echo "   3. You should see the services listed above\n";
echo "   4. Click on a service to see its transactions\n";

echo "\n6. Test the web interface:\n";
echo "   1. Open http://localhost:8888 in your browser\n";
echo "   2. Click the buttons to generate APM events\n";
echo "   3. Check Kibana to see the events appear\n";

echo "\nDone!\n";