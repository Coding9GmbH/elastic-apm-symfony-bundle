<?php

namespace ElasticApmBundle\Tests\Unit\DependencyInjection;

use ElasticApmBundle\DependencyInjection\Configuration;
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
                ],
                'rum' => [
                    'enabled' => true,
                    'server_url' => 'http://rum-server:8200',
                    'service_name' => 'my-frontend'
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
        $this->assertTrue($config['rum']['enabled']);
        $this->assertEquals('http://rum-server:8200', $config['rum']['server_url']);
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