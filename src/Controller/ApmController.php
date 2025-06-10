<?php
// Copyright (c) 2025 Coding 9 GmbH
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of
// this software and associated documentation files (the "Software"), to deal in
// the Software without restriction, including without limitation the rights to
// use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
// the Software, and to permit persons to whom the Software is furnished to do so,
// subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
// COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
// IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
// CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.


namespace ElasticApmBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * APM Controller for frontend RUM configuration
 * 
 * SECURITY WARNING: This endpoint exposes service configuration.
 * Only enable RUM if you need frontend monitoring and ensure proper access controls.
 */
class ApmController extends AbstractController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * RUM configuration endpoint for frontend APM
     * 
     * Security considerations:
     * - Only returns data when RUM is explicitly enabled
     * - Requires authentication (uncomment @IsGranted to enforce)
     * - Should be restricted to authorized users/origins
     * - Consider CORS policies for this endpoint
     */
    #[Route('/api/apm/rum-config', name: 'elastic_apm_rum_config', methods: ['GET'])]
    // #[IsGranted('ROLE_USER')] // Uncomment to require authentication
    public function rumConfig(Request $request): JsonResponse
    {
        // Security check: Only allow if RUM is explicitly enabled
        if (!$this->config['rum']['enabled']) {
            return new JsonResponse(['enabled' => false], 200);
        }

        // Optional: Check if request comes from allowed origins
        // $origin = $request->headers->get('Origin');
        // if (!$this->isAllowedOrigin($origin)) {
        //     return new JsonResponse(['error' => 'Forbidden'], 403);
        // }

        // Optional: Rate limiting check
        // if (!$this->isWithinRateLimit($request->getClientIp())) {
        //     return new JsonResponse(['error' => 'Rate limit exceeded'], 429);
        // }

        // Return minimal configuration for frontend RUM
        return new JsonResponse([
            'enabled' => true,
            'serviceName' => $this->config['rum']['service_name'], // Safe: public service name
            'serverUrl' => $this->config['rum']['server_url'],      // Caution: APM endpoint
            // Removed: serviceVersion and environment (internal details)
        ]);
    }

    /**
     * Check if origin is allowed for RUM configuration
     */
    private function isAllowedOrigin(?string $origin): bool
    {
        if (!$origin) {
            return false; // Reject requests without Origin header
        }

        // Define allowed origins for RUM configuration
        $allowedOrigins = [
            'https://your-frontend-domain.com',
            'https://app.your-domain.com',
            // Add your actual frontend domains
        ];

        return in_array($origin, $allowedOrigins);
    }

    /**
     * Simple rate limiting check
     */
    private function isWithinRateLimit(string $ip): bool
    {
        // Implement rate limiting logic
        // This is a placeholder - use proper rate limiting in production
        return true;
    }
}