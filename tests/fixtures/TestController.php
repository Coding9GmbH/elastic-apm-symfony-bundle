<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController
{
    #[Route('/', name: 'home')]
    public function home(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Welcome to APM Test App',
            'timestamp' => time(),
            'service' => 'symfony-demo'
        ]);
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
        throw new \Exception('Test exception for APM');
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
}