#!/usr/bin/env php
<?php

echo "Testing with Official Elastic APM PHP Agent\n";
echo "===========================================\n\n";

// Check if official agent is available
if (!class_exists('\Elastic\Apm\ElasticApm')) {
    echo "The official Elastic APM PHP agent is not installed.\n";
    echo "To use it, run:\n";
    echo "  composer require elastic/apm-agent-php\n\n";
    echo "Benefits of using the official agent:\n";
    echo "- Maintained by Elastic with regular updates\n";
    echo "- Better performance and lower overhead\n";
    echo "- Full compatibility with APM Server features\n";
    echo "- Built-in integrations for popular frameworks\n";
    echo "- Automatic instrumentation capabilities\n\n";
    exit(1);
}

// If we had the official agent installed, usage would be:
echo "Example usage with official agent:\n\n";
echo <<<'PHP'
$agent = \Elastic\Apm\ElasticApm::builder([
    'serviceName' => 'my-symfony-app',
    'environment' => 'production',
    'serverUrl' => 'http://localhost:8200',
    'secretToken' => 'optional-token',
])->build();

// Start a transaction
$transaction = $agent->startTransaction('GET /users', 'request');

try {
    // Add custom context
    $transaction->setLabel('user_id', 123);
    $transaction->setContext([
        'custom' => ['key' => 'value'],
        'user' => ['id' => 123, 'username' => 'john'],
    ]);
    
    // Create a span for database query
    $span = $agent->startSpan('SELECT * FROM users', 'db.mysql.query');
    // ... database work ...
    $span->end();
    
    // Your application logic here
    
} catch (\Exception $e) {
    // Capture exceptions
    $agent->captureException($e);
    throw $e;
} finally {
    // End transaction
    $transaction->end();
}
PHP;

echo "\n\nThe current bundle implementation doesn't leverage the official agent's features.\n";
echo "A proper Symfony bundle should:\n";
echo "1. Use elastic/apm-agent-php as a dependency\n";
echo "2. Provide Symfony-specific configuration\n";
echo "3. Add automatic instrumentation for Symfony components\n";
echo "4. Handle the agent lifecycle properly\n";