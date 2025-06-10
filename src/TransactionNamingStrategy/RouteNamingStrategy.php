<?php

namespace ElasticApmBundle\TransactionNamingStrategy;

use Symfony\Component\HttpFoundation\Request;

/**
 * Names transactions based on the route name
 */
class RouteNamingStrategy implements TransactionNamingStrategyInterface
{
    public function getTransactionName(Request $request): string
    {
        $routeName = $request->attributes->get('_route', 'unknown');
        $method = $request->getMethod();

        return sprintf('%s %s', $method, $routeName);
    }
}