<?php
require_once "vendor/autoload.php";
use Coding9\ElasticApmBundle\Client\ApmClient;
use Coding9\ElasticApmBundle\Model\Transaction;
use Psr\Log\AbstractLogger;

class DebugLogger extends AbstractLogger {
    public function log($level, $message, array $context = []): void {
        echo "[" . strtoupper($level) . "] $message\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

$config = [
    "enabled" => true,
    "server" => ["url" => "http://apm-server:8200"],
    "service" => ["name" => "debug-test", "environment" => "test"],
    "transactions" => ["sample_rate" => 1.0]
];

$logger = new DebugLogger();
$client = new ApmClient($config, $logger);

echo "Creating transaction...\n";
$transaction = new Transaction("TEST Debug", "request");
$transaction->stop();
$transaction->setResult("success");

echo "\nSending transaction...\n";
$client->sendTransaction($transaction);

echo "\nFlushing...\n";
$client->flush();

echo "\nDone!\n";