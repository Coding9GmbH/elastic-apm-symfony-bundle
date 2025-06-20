#\!/usr/bin/env php
<?php

echo "Verifying APM Data in Elasticsearch\n";
echo "===================================\n\n";

// Check Elasticsearch
$esUrl = 'http://localhost:9200';
$ch = curl_init("$esUrl/apm-*-transaction-*/_search?size=10&sort=@timestamp:desc");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode \!== 200) {
    echo "❌ Failed to connect to Elasticsearch\n";
    exit(1);
}

$data = json_decode($response, true);
$total = $data['hits']['total']['value'] ?? 0;

echo "📊 Total transactions in Elasticsearch: $total\n\n";

if ($total > 0) {
    echo "Recent transactions:\n";
    foreach ($data['hits']['hits'] as $hit) {
        $source = $hit['_source'];
        $name = $source['transaction']['name'] ?? 'Unknown';
        $duration = $source['transaction']['duration']['us'] ?? 0;
        $timestamp = $source['@timestamp'] ?? '';
        $service = $source['service']['name'] ?? 'Unknown';
        
        $durationMs = round($duration / 1000, 2);
        echo "  • [$service] $name - {$durationMs}ms - $timestamp\n";
    }
}

// Check for errors
echo "\n";
$ch = curl_init("$esUrl/apm-*-error-*/_search?size=5&sort=@timestamp:desc");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$errorCount = $data['hits']['total']['value'] ?? 0;

echo "🚨 Total errors: $errorCount\n";

if ($errorCount > 0) {
    echo "Recent errors:\n";
    foreach ($data['hits']['hits'] as $hit) {
        $source = $hit['_source'];
        $message = $source['error']['exception'][0]['message'] ?? $source['error']['message'] ?? 'Unknown error';
        $service = $source['service']['name'] ?? 'Unknown';
        echo "  • [$service] $message\n";
    }
}

echo "\n✅ You can view this data in Kibana at: http://localhost:5601\n";
echo "   Navigate to: Observability > APM\n";
EOF < /dev/null
