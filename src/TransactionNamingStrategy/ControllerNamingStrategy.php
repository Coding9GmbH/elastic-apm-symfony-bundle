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

        // Extract class and method from controller string
        if (str_contains($controller, '::')) {
            [$class, $action] = explode('::', $controller, 2);
            $class = class_basename($class);
            return sprintf('%s %s::%s', $method, $class, $action);
        }

        return sprintf('%s %s', $method, $controller);
    }
}

function class_basename($class): string
{
    $class = is_object($class) ? get_class($class) : $class;
    return basename(str_replace('\\', '/', $class));
}