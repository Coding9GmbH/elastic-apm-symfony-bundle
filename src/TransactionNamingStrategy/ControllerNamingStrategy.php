<?php

namespace ElasticApmBundle\TransactionNamingStrategy;

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