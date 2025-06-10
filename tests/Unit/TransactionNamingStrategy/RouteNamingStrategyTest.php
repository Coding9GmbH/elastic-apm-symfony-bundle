<?php

namespace ElasticApmBundle\Tests\Unit\TransactionNamingStrategy;

use ElasticApmBundle\TransactionNamingStrategy\RouteNamingStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RouteNamingStrategyTest extends TestCase
{
    /** @var RouteNamingStrategy */
    private $strategy;

    protected function setUp(): void
    {
        $this->strategy = new RouteNamingStrategy();
    }

    public function testGetTransactionNameWithRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'app_home_index');

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET app_home_index', $name);
    }

    public function testGetTransactionNameWithoutRoute(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/test-uri');

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET unknown', $name);
    }

    public function testGetTransactionNameWithEmptyUri(): void
    {
        $request = new Request();

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET unknown', $name);
    }

    public function testGetTransactionNameWithQueryString(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/test-uri?param=value');

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET unknown', $name);
    }
}