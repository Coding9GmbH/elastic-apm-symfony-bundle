<?php

namespace ElasticApmBundle\Tests;

use Symfony\Component\HttpKernel\HttpKernelInterface;

class SymfonyCompat
{
    public static function getMainRequestType(): int
    {
        // Use MAIN_REQUEST if available (Symfony 5.3+), otherwise fall back to MASTER_REQUEST
        return defined(HttpKernelInterface::class . '::MAIN_REQUEST')
            ? HttpKernelInterface::MAIN_REQUEST
            : HttpKernelInterface::MASTER_REQUEST;
    }
}