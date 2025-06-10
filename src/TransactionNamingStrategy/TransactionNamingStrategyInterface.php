<?php

namespace ElasticApmBundle\TransactionNamingStrategy;

use Symfony\Component\HttpFoundation\Request;

interface TransactionNamingStrategyInterface
{
    /**
     * Get the transaction name from the request
     */
    public function getTransactionName(Request $request): string;
}