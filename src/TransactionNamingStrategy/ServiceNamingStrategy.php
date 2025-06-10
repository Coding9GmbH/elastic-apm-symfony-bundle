<?php

namespace ElasticApmBundle\TransactionNamingStrategy;

use Symfony\Component\HttpFoundation\Request;

/**
 * Names transactions with a custom service-based naming convention
 */
class ServiceNamingStrategy implements TransactionNamingStrategyInterface
{
    private string $serviceName;

    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
    }

    public function getTransactionName(Request $request): string
    {
        $routeName = $request->attributes->get('_route', 'unknown');
        $method = $request->getMethod();

        return sprintf('%s.%s.%s', $this->serviceName, strtolower($method), $routeName);
    }
}