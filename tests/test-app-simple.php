#!/usr/bin/env php
<?php

echo "Setting up test environment...\n";

// Check if containers are running
$output = shell_exec('docker ps --filter name=symfony-test-app --format "{{.Status}}"');
if (empty(trim($output))) {
    echo "Error: symfony-test-app container is not running. Run ./tests/start-test-app.sh first.\n";
    exit(1);
}

echo "Test application is available at: http://localhost:8888\n\n";
echo "Test scenarios:\n";
echo "1. Open http://localhost:8888 in your browser\n";
echo "2. Click 'Fetch Users' button - triggers a normal APM transaction\n";
echo "3. Click 'Slow Request' button - triggers a slow transaction (2s)\n";
echo "4. Click 'Trigger Error' button - triggers an error that APM will capture\n";
echo "5. Click 'Send Message to Queue' button - sends a message to be processed asynchronously\n\n";
echo "View APM data in Kibana: http://localhost:5601\n";
echo " - Navigate to Observability > APM\n";
echo " - Look for service: symfony-demo\n\n";

// Run a simple test to verify the setup
echo "Running connectivity test...\n";
$ch = curl_init('http://localhost:8888/api/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✓ Test endpoint is responding correctly\n";
    $data = json_decode($response, true);
    if (isset($data['users'])) {
        echo "✓ API returned expected data structure\n";
    }
} else {
    echo "✗ Test endpoint returned HTTP $httpCode\n";
    echo "  This might be due to the APM bundle not being properly configured.\n";
    echo "  You can still test with a basic Symfony app without APM.\n";
}

echo "\nTo generate test data, you can run:\n";
echo "  curl http://localhost:8888/api/users\n";
echo "  curl http://localhost:8888/api/slow\n";
echo "  curl -X POST http://localhost:8888/api/send-message\n";