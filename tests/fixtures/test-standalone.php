<?php
// Standalone test file that can be run without Symfony
require_once __DIR__ . '/../../vendor/autoload.php';

use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractor;
use Coding9\ElasticApmBundle\Client\ApmClient;

// Simple HTML interface
if (!isset($_GET['action'])) {
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>APM Test - Standalone</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        button { 
            padding: 10px 20px; 
            margin: 10px;
            font-size: 16px;
            cursor: pointer;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
        }
        button:hover { background-color: #0056b3; }
        .response { 
            margin-top: 20px; 
            padding: 10px; 
            background-color: #f8f9fa; 
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>APM Test Application - Standalone</h1>
    <p>This is a simple test that demonstrates APM tracking without full Symfony integration.</p>
    
    <div>
        <button onclick="window.location.href='?action=normal'">Normal Request</button>
        <button onclick="window.location.href='?action=slow'">Slow Request (2s)</button>
        <button onclick="window.location.href='?action=error'">Trigger Error</button>
    </div>
    
    <div class="response">
        Click a button to trigger an APM event.
    </div>
</body>
</html>
    <?php
    exit;
}

// Initialize APM
$config = [
    'server' => ['url' => 'http://apm-server:8200'],
    'service' => [
        'name' => 'standalone-test',
        'environment' => 'test'
    ]
];

$client = new ApmClient($config);
$interactor = new ElasticApmInteractor($client);

// Start transaction
$transaction = $interactor->startTransaction('test.request', $_GET['action'] ?? 'unknown');

try {
    switch ($_GET['action']) {
        case 'normal':
            // Simulate some work
            usleep(50000); // 50ms
            echo json_encode(['status' => 'success', 'message' => 'Normal request completed']);
            break;
            
        case 'slow':
            // Simulate slow operation
            sleep(2);
            echo json_encode(['status' => 'success', 'message' => 'Slow request completed (2s)']);
            break;
            
        case 'error':
            throw new Exception('Test exception for APM');
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    $interactor->captureException($e);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $interactor->stopTransaction();
    $interactor->send();
}