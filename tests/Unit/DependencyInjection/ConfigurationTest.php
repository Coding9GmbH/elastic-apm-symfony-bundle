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


namespace Coding9\ElasticApmBundle\Tests\Unit\DependencyInjection;

use Coding9\ElasticApmBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    /** @var Configuration */
    private $configuration;

    /** @var Processor */
    private $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertArrayHasKey('enabled', $config);
        $this->assertTrue($config['enabled']);
        $this->assertArrayHasKey('service', $config);
        $this->assertArrayHasKey('name', $config['service']);
        $this->assertArrayHasKey('server', $config);
        $this->assertArrayHasKey('url', $config['server']);
        $this->assertArrayHasKey('secret_token', $config['server']);
        $this->assertArrayHasKey('transactions', $config);
        $this->assertArrayHasKey('naming_strategy', $config['transactions']);
        $this->assertEquals('route', $config['transactions']['naming_strategy']);
    }

    public function testCustomConfiguration(): void
    {
        $customConfig = [
            'elastic_apm' => [
                'enabled' => false,
                'service' => [
                    'name' => 'my-service',
                    'environment' => 'production'
                ],
                'server' => [
                    'url' => 'http://apm-server:8200',
                    'secret_token' => 'secret123'
                ],
                'transactions' => [
                    'naming_strategy' => 'controller'
                ]
            ]
        ];

        $config = $this->processor->processConfiguration($this->configuration, $customConfig);

        $this->assertFalse($config['enabled']);
        $this->assertEquals('my-service', $config['service']['name']);
        $this->assertEquals('http://apm-server:8200', $config['server']['url']);
        $this->assertEquals('secret123', $config['server']['secret_token']);
        $this->assertEquals('production', $config['service']['environment']);
        $this->assertEquals('controller', $config['transactions']['naming_strategy']);
    }

    public function testInvalidTransactionNamingStrategy(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $invalidConfig = [
            'elastic_apm' => [
                'transactions' => [
                    'naming_strategy' => 'invalid_strategy'
                ]
            ]
        ];

        $this->processor->processConfiguration($this->configuration, $invalidConfig);
    }
}