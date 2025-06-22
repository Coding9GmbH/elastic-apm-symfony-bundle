#!/usr/bin/env php
<?php

echo "Testing Message Queue APM Scenarios\n";
echo "===================================\n\n";

$scenarios = [
    ['type' => 'success', 'data' => 'Normal message that will succeed'],
    ['type' => 'fail', 'data' => 'This message will fail validation'],
    ['type' => 'error', 'data' => 'This message will cause an error in external API'],
];

foreach ($scenarios as $scenario) {
    echo "Testing {$scenario['type']} scenario...\n";
    
    // Send message
    $ch = curl_init("http://localhost:8888/api/send-message");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['data' => $scenario['data']]),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "  Response (HTTP $httpCode): $response\n";
    
    // Wait a bit between requests
    sleep(2);
}

echo "\n\nWaiting for data to be indexed...\n";
sleep(5);

// Check APM data
echo "\nChecking APM data in Elasticsearch:\n";

$query = [
    "query" => [
        "bool" => [
            "must" => [
                ["term" => ["service.name" => "symfony-test-app"]],
                ["range" => ["@timestamp" => ["gte" => "now-1m"]]]
            ]
        ]
    ],
    "size" => 50,
    "sort" => [["@timestamp" => "desc"]],
    "_source" => ["@timestamp", "transaction.name", "span.name", "processor.event", "error.exception.message"]
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

echo "\nRecent APM events:\n";
$transactions = [];
$spans = [];
$errors = [];

foreach ($data['hits']['hits'] as $hit) {
    $source = $hit['_source'];
    $event = $source['processor']['event'];
    
    if ($event === 'transaction') {
        $transactions[] = $source['transaction']['name'];
    } elseif ($event === 'span') {
        $spans[] = $source['span']['name'];
    } elseif ($event === 'error') {
        $errors[] = $source['error']['exception'][0]['message'] ?? 'Unknown error';
    }
}

echo "\nTransactions: " . implode(", ", array_unique($transactions)) . "\n";
echo "Spans: " . implode(", ", array_unique($spans)) . "\n";
echo "Errors: " . implode(", ", array_unique($errors)) . "\n";

echo "\nYou should see in Kibana APM:\n";
echo "- Multiple spans in the timeline for each message:\n";
echo "  - Validate message (20ms)\n";
echo "  - Database query (50ms)\n";
echo "  - External API call (80ms)\n";
echo "  - Cache operation (10ms)\n";
echo "- Errors for the 'fail' and 'error' scenarios\n";
echo "- Rich metadata and context for each transaction\n";

echo "\nDone!\n";