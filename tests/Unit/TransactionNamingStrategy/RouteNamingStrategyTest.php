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