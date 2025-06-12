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
 * Names transactions based on the controller class and method
 */
class ControllerNamingStrategy implements TransactionNamingStrategyInterface
{
    public function getTransactionName(Request $request): string
    {
        $controller = $request->attributes->get('_controller', 'unknown');
        $method = $request->getMethod();

        // Handle different controller formats
        if (is_array($controller)) {
            // Handle ['ClassName', 'methodName'] or [$object, 'methodName']
            if (count($controller) === 2) {
                $class = is_object($controller[0]) ? get_class($controller[0]) : $controller[0];
                $class = class_basename($class);
                return sprintf('%s %s::%s', $method, $class, $controller[1]);
            }
        } elseif (is_object($controller)) {
            // Handle invokable objects and closures
            if ($controller instanceof \Closure) {
                return sprintf('%s Closure', $method);
            }
            $class = get_class($controller);
            // Check if it's an anonymous class
            if (str_contains($class, '@anonymous')) {
                return sprintf('%s class@anonymous', $method);
            }
            return sprintf('%s %s', $method, class_basename($class));
        } elseif (is_string($controller)) {
            // Handle string controller notation
            if (str_contains($controller, '::')) {
                [$class, $action] = explode('::', $controller, 2);
                $class = class_basename($class);
                return sprintf('%s %s::%s', $method, $class, $action);
            }
        }

        return sprintf('%s %s', $method, $controller);
    }
}

function class_basename($class): string
{
    $class = is_object($class) ? get_class($class) : $class;
    return basename(str_replace('\\', '/', $class));
}