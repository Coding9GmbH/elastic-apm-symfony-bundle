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
        $this->assertArrayHasKey('service_name', $config);
        $this->assertArrayHasKey('server_url', $config);
        $this->assertArrayHasKey('secret_token', $config);
        $this->assertArrayHasKey('environment', $config);
        $this->assertArrayHasKey('transaction_naming_strategy', $config);
        $this->assertEquals('route', $config['transaction_naming_strategy']);
    }

    public function testCustomConfiguration(): void
    {
        $customConfig = [
            'elastic_apm' => [
                'enabled' => false,
                'service_name' => 'my-service',
                'server_url' => 'http://apm-server:8200',
                'secret_token' => 'secret123',
                'environment' => 'production',
                'transaction_naming_strategy' => 'controller',
                'rum' => [
                    'enabled' => true,
                    'server_url' => 'http://rum-server:8200',
                    'service_name' => 'my-frontend',
                    'environment' => 'production'
                ]
            ]
        ];

        $config = $this->processor->processConfiguration($this->configuration, $customConfig);

        $this->assertFalse($config['enabled']);
        $this->assertEquals('my-service', $config['service_name']);
        $this->assertEquals('http://apm-server:8200', $config['server_url']);
        $this->assertEquals('secret123', $config['secret_token']);
        $this->assertEquals('production', $config['environment']);
        $this->assertEquals('controller', $config['transaction_naming_strategy']);
        $this->assertTrue($config['rum']['enabled']);
        $this->assertEquals('http://rum-server:8200', $config['rum']['server_url']);
    }

    public function testInvalidTransactionNamingStrategy(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $invalidConfig = [
            'elastic_apm' => [
                'transaction_naming_strategy' => 'invalid_strategy'
            ]
        ];

        $this->processor->processConfiguration($this->configuration, $invalidConfig);
    }
}