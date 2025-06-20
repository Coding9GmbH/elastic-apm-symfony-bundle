#!/usr/bin/env php
<?php

echo "Testing Symfony App APM Integration\n";
echo "===================================\n\n";

$endpoints = [
    '/api/users' => 'GET Users',
    '/api/slow' => 'Slow Request',
    '/api/error' => 'Error Request',
];

foreach ($endpoints as $endpoint => $name) {
    echo "Testing $name ($endpoint)...\n";
    
    $ch = curl_init("http://localhost:8888$endpoint");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "  HTTP Status: $httpCode\n";
    
    // Small delay between requests
    usleep(500000);
}

echo "\nWaiting for data to be indexed...\n";
sleep(5);

// Check for all services
echo "\nServices in APM:\n";
$ch = curl_init('http://localhost:9200/apm-*/_search');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode([
        "size" => 0,
        "aggs" => [
            "services" => [
                "terms" => ["field" => "service.name", "size" => 20],
                "aggs" => [
                    "latest" => ["max" => ["field" => "@timestamp"]]
                ]
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
        $latest = date('Y-m-d H:i:s', intval($bucket['latest']['value'] / 1000));
        echo "  - " . $bucket['key'] . " (" . $bucket['doc_count'] . " docs, latest: $latest)\n";
    }
}

// Check specifically for symfony-test-app recent data
echo "\nChecking for recent symfony-test-app transactions...\n";
$ch = curl_init('http://localhost:9200/apm-*-transaction-*/_search');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode([
        "query" => [
            "bool" => [
                "must" => [
                    ["term" => ["service.name" => "symfony-test-app"]],
                    ["range" => ["@timestamp" => ["gte" => "now-5m"]]]
                ]
            ]
        ],
        "size" => 5,
        "sort" => [["@timestamp" => "desc"]]
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

$count = $data['hits']['total']['value'] ?? 0;
echo "  Found $count recent transactions\n";

if ($count === 0) {
    echo "\n❌ No recent APM data from symfony-test-app\n";
    echo "\nPossible issues:\n";
    echo "1. The APM bundle might not be sending data\n";
    echo "2. The interactor might be disabled\n";
    echo "3. There might be a configuration issue\n";
} else {
    echo "\n✅ APM integration is working!\n";
    echo "View in Kibana: http://localhost:5601 > Observability > APM > symfony-test-app\n";
}