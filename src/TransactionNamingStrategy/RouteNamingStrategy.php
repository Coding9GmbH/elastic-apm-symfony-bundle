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


namespace Coding9\ElasticApmBundle\TransactionNamingStrategy;

use Symfony\Component\HttpFoundation\Request;

/**
 * Names transactions based on the route name
 */
class RouteNamingStrategy implements TransactionNamingStrategyInterface
{
    public function getTransactionName(Request $request): string
    {
        $routeName = $request->attributes->get('_route');
        $method = $request->getMethod();

        // If we have a route name, use it
        if ($routeName) {
            // Remove common prefixes/suffixes for cleaner names
            $routeName = preg_replace('/^(app_|admin_|api_)/', '', $routeName);
            $routeName = preg_replace('/(_index|_show|_create|_update|_delete)$/', '', $routeName);
            
            return sprintf('%s %s', $method, $routeName);
        }

        // Try to get controller information
        $controller = $request->attributes->get('_controller');
        if ($controller) {
            // Handle different controller formats
            if (str_contains($controller, '::')) {
                // Format: App\Controller\HomeController::index
                $parts = explode('::', $controller);
                $className = basename(str_replace('\\', '/', $parts[0]));
                $methodName = $parts[1] ?? 'unknown';
                
                // Remove "Controller" suffix
                $className = preg_replace('/Controller$/', '', $className);
                
                return sprintf('%s %s::%s', $method, $className, $methodName);
            }
        }

        // Fallback to path-based naming
        $path = $request->getPathInfo();
        if ($path && $path !== '/') {
            // Clean up path for better readability
            $path = trim($path, '/');
            $path = preg_replace('/\d+/', '{id}', $path); // Replace IDs with placeholder
            
            return sprintf('%s /%s', $method, $path);
        }

        return sprintf('%s /', $method);
    }
}