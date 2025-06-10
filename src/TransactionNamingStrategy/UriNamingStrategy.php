<?php

namespace ElasticApmBundle\TransactionNamingStrategy;

use Symfony\Component\HttpFoundation\Request;

/**
 * Names transactions based on the URI path pattern
 */
class UriNamingStrategy implements TransactionNamingStrategyInterface
{
    public function getTransactionName(Request $request): string
    {
        $method = $request->getMethod();
        $pathInfo = $request->getPathInfo();

        // Replace dynamic segments with placeholders
        $pattern = preg_replace('/\/\d+/', '/{id}', $pathInfo);
        $pattern = preg_replace('/\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/', '/{uuid}', $pattern);

        return sprintf('%s %s', $method, $pattern);
    }
}