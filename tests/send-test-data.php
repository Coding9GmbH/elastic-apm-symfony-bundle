<?php

/**
 * Einfaches Script zum Senden von Test-Daten an APM
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractor;

$config = [
    'enabled' => true,
    'server_url' => getenv('ELASTIC_APM_SERVER_URL') ?: 'http://apm-server:8200',
    'service_name' => 'test-script',
    'environment' => 'test',
    'transaction_sample_rate' => 1.0,
    'metadata' => [
        'service' => [
            'name' => 'test-script',
            'environment' => 'test',
            'version' => '1.0.0',
            'agent' => [
                'name' => 'elastic-apm-symfony',
                'version' => '1.0.0'
            ]
        ]
    ]
];

$apm = new ElasticApmInteractor($config);

// Simuliere verschiedene Transaktionen
$scenarios = [
    ['name' => 'GET /api/users', 'duration' => 120],
    ['name' => 'POST /api/order', 'duration' => 340],
    ['name' => 'GET /products', 'duration' => 89],
    ['name' => 'DELETE /cache', 'duration' => 45],
];

foreach ($scenarios as $scenario) {
    $transaction = $apm->startTransaction($scenario['name'], 'request');
    
    // Füge Spans hinzu
    $dbSpan = $apm->startSpan('db.query', 'db', 'SELECT * FROM users');
    usleep($scenario['duration'] * 500); // Simuliere DB-Zeit
    $apm->stopSpan($dbSpan);
    
    $cacheSpan = $apm->startSpan('cache.get', 'cache', 'users:all');
    usleep(5000); // 5ms Cache
    $apm->stopSpan($cacheSpan);
    
    // Füge Context hinzu
    $apm->setUserContext([
        'id' => rand(1, 100),
        'username' => 'test_user_' . rand(1, 10)
    ]);
    
    $apm->setCustomContext([
        'request_id' => uniqid(),
        'feature_flag' => 'new_ui_enabled'
    ]);
    
    usleep($scenario['duration'] * 1000);
    $apm->stopTransaction($transaction);
    
    echo "✅ Sent: " . $scenario['name'] . " (duration: " . $scenario['duration'] . "ms)\n";
}

// Sende auch einen Fehler
try {
    $errorTransaction = $apm->startTransaction('GET /api/broken', 'request');
    throw new Exception('Database connection failed');
} catch (Exception $e) {
    $apm->captureError($e->getMessage(), [
        'exception' => [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'stacktrace' => $e->getTraceAsString()
        ]
    ]);
    $apm->stopTransaction($errorTransaction);
    echo "✅ Sent: Error transaction\n";
}

$apm->flush();
echo "\n🎉 All test data sent to APM!\n";