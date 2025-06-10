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

        $this->assertEquals('GET HomeController::index', $name);
    }

    public function testGetTransactionNameWithCallableArrayController(): void
    {
        $request = new Request();
        $controller = ['App\Controller\HomeController', 'index'];
        $request->attributes->set('_controller', $controller);

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET HomeController::index', $name);
    }

    public function testGetTransactionNameWithObjectController(): void
    {
        $request = new Request();
        $controller = new \stdClass();
        $request->attributes->set('_controller', [$controller, 'index']);

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET stdClass::index', $name);
    }

    public function testGetTransactionNameWithClosure(): void
    {
        $request = new Request();
        $request->attributes->set('_controller', function() {});

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET Closure', $name);
    }

    public function testGetTransactionNameWithInvokableObject(): void
    {
        $request = new Request();
        $controller = new class {
            public function __invoke() {}
        };
        $request->attributes->set('_controller', $controller);

        $name = $this->strategy->getTransactionName($request);

        $this->assertEquals('GET class@anonymous', $name);
    }

    public function testGetTransactionNameWithoutController(): void
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
}