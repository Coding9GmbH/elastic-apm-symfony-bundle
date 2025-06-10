<?php

namespace ElasticApmBundle\Tests\Unit\TransactionNamingStrategy;

use ElasticApmBundle\TransactionNamingStrategy\ControllerNamingStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ControllerNamingStrategyTest extends TestCase
{
    /** @var ControllerNamingStrategy */
    private $strategy;

    protected function setUp(): void
    {
        $this->strategy = new ControllerNamingStrategy();
    }

    public function testGetTransactionNameWithControllerAttribute(): void
    {
        $request = new Request();
        $request->attributes->set('_controller', 'App\Controller\HomeController::index');

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('App\Controller\HomeController::index', $name);
    }

    public function testGetTransactionNameWithCallableArrayController(): void
    {
        $request = new Request();
        $controller = ['App\Controller\HomeController', 'index'];
        $request->attributes->set('_controller', $controller);

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('App\Controller\HomeController::index', $name);
    }

    public function testGetTransactionNameWithObjectController(): void
    {
        $request = new Request();
        $controller = new \stdClass();
        $request->attributes->set('_controller', [$controller, 'index']);

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('stdClass::index', $name);
    }

    public function testGetTransactionNameWithClosure(): void
    {
        $request = new Request();
        $request->attributes->set('_controller', function() {});

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('Closure', $name);
    }

    public function testGetTransactionNameWithInvokableObject(): void
    {
        $request = new Request();
        $controller = new class {
            public function __invoke() {}
        };
        $request->attributes->set('_controller', $controller);

        $name = $this->strategy->getTransactionName($request);

        $this->assertStringContains('class@anonymous', $name);
    }

    public function testGetTransactionNameWithoutController(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/test-uri');

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('/test-uri', $name);
    }

    public function testGetTransactionNameWithEmptyUri(): void
    {
        $request = new Request();

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('/', $name);
    }
}