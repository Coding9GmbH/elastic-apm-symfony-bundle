<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\ProcessDataMessage;

class TestController
{
    public function __construct(
        private ?MessageBusInterface $messageBus = null
    ) {}

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return new Response(<<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>APM Test Application</title>
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
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <h1>APM Test Application</h1>
    <p>Click the buttons below to trigger different APM events:</p>
    
    <div>
        <button onclick="fetchData('/api/users')">Fetch Users (Normal Request)</button>
        <button onclick="fetchData('/api/slow')">Slow Request (2s delay)</button>
        <button onclick="fetchData('/api/error')">Trigger Error</button>
    </div>
    
    <div style="margin-top: 20px;">
        <h3>Message Queue Tests:</h3>
        <button onclick="sendMessage('success')">Send Success Message</button>
        <button onclick="sendMessage('fail')">Send Failing Message (Validation Error)</button>
        <button onclick="sendMessage('error')">Send Error Message (API Error)</button>
    </div>
    
    <div id="response" class="response" style="display:none;"></div>
    
    <script>
        function fetchData(url) {
            const responseDiv = document.getElementById('response');
            responseDiv.style.display = 'block';
            responseDiv.className = 'response';
            responseDiv.innerHTML = 'Loading...';
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: \${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    responseDiv.innerHTML = '<strong>Success:</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    responseDiv.className = 'response error';
                    responseDiv.innerHTML = '<strong>Error:</strong> ' + error.message;
                });
        }
        
        function sendMessage(type) {
            const responseDiv = document.getElementById('response');
            responseDiv.style.display = 'block';
            responseDiv.className = 'response';
            responseDiv.innerHTML = 'Sending message to queue...';
            
            let messageText = 'Test message at ' + new Date().toISOString();
            if (type === 'fail') {
                messageText = 'This message will fail validation at ' + new Date().toISOString();
            } else if (type === 'error') {
                messageText = 'This message will cause an error at ' + new Date().toISOString();
            }
            
            fetch('/api/send-message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    data: messageText
                })
            })
            .then(response => response.json())
            .then(data => {
                responseDiv.innerHTML = '<strong>Message sent:</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>' +
                    '<p>Check Kibana APM to see the ' + type + ' message processing timeline!</p>';
            })
            .catch(error => {
                responseDiv.className = 'response error';
                responseDiv.innerHTML = '<strong>Error:</strong> ' + error.message;
            });
        }
    </script>
</body>
</html>
HTML);
    }

    #[Route('/api/users', name: 'users')]
    public function users(): JsonResponse
    {
        // Simuliere Datenbank-Abfrage
        usleep(50000); // 50ms
        
        return new JsonResponse([
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
                ['id' => 3, 'name' => 'Charlie']
            ],
            'total' => 3
        ]);
    }

    #[Route('/api/slow', name: 'slow')]
    public function slow(): JsonResponse
    {
        // Simuliere langsame Operation
        sleep(2);
        
        return new JsonResponse([
            'message' => 'This was slow!',
            'duration' => '2 seconds'
        ]);
    }

    #[Route('/api/error', name: 'error')]
    public function error(): Response
    {
        // Simulate some work before error
        usleep(20000); // 20ms
        
        // Create a nested exception to test full stack trace
        try {
            $this->performDangerousOperation();
        } catch (\Exception $e) {
            throw new \RuntimeException('Error in API endpoint', 500, $e);
        }
    }
    
    private function performDangerousOperation(): void
    {
        usleep(10000); // 10ms
        $this->callDeepMethod();
    }
    
    private function callDeepMethod(): void
    {
        usleep(5000); // 5ms
        throw new \InvalidArgumentException('Something went wrong deep in the application stack');
    }

    #[Route('/api/random-status', name: 'random_status')]
    public function randomStatus(): Response
    {
        $statuses = [200, 201, 400, 404, 500];
        $status = $statuses[array_rand($statuses)];
        
        return new JsonResponse(
            ['status' => $status],
            $status
        );
    }

    #[Route('/api/send-message', name: 'send_message', methods: ['POST'])]
    public function sendMessage(): JsonResponse
    {
        if (!$this->messageBus) {
            return new JsonResponse([
                'error' => 'Message bus not configured',
                'hint' => 'Install symfony/messenger: composer require symfony/messenger'
            ], 503);
        }

        $message = new ProcessDataMessage(
            'test-' . uniqid(),
            ['timestamp' => time(), 'source' => 'web']
        );
        
        $this->messageBus->dispatch($message);
        
        return new JsonResponse([
            'status' => 'Message dispatched',
            'message_id' => $message->getId(),
            'timestamp' => time()
        ]);
    }
}