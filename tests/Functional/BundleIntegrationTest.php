<?php

namespace Coding9\ElasticApmBundle\Tests\Functional;

use Coding9\ElasticApmBundle\ElasticApmBundle;
use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class BundleIntegrationTest extends TestCase
{
    public function testBundleLoads(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        
        $container = $kernel->getContainer();
        
        // Check if bundle is loaded
        $bundles = $kernel->getBundles();
        $this->assertArrayHasKey('ElasticApmBundle', $bundles);
        $this->assertInstanceOf(ElasticApmBundle::class, $bundles['ElasticApmBundle']);
    }
    
    public function testInteractorServiceExists(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        
        $container = $kernel->getContainer();
        
        // Check if APM interactor service is available
        $this->assertTrue($container->has(ElasticApmInteractorInterface::class));
        
        $interactor = $container->get(ElasticApmInteractorInterface::class);
        $this->assertInstanceOf(ElasticApmInteractorInterface::class, $interactor);
    }
    
    public function testApmFunctionality(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        
        $container = $kernel->getContainer();
        $apm = $container->get(ElasticApmInteractorInterface::class);
        
        // Test basic APM operations
        $transaction = $apm->startTransaction('Test Transaction', 'phpunit');
        $this->assertNotNull($transaction);
        $this->assertEquals('Test Transaction', $transaction->getName());
        
        $span = $apm->startSpan('Test Span', 'test');
        $this->assertNotNull($span);
        $this->assertEquals('Test Span', $span->getName());
        
        // Test user context
        $apm->setUserContext([
            'id' => '123',
            'email' => 'test@example.com'
        ]);
        
        // Test error capture
        $apm->captureError('Test error', ['source' => 'phpunit']);
        
        // Complete span and transaction
        $apm->stopSpan($span);
        $apm->stopTransaction($transaction, 200);
        
        // If we get here without exceptions, the bundle works
        $this->assertTrue(true);
    }
}

class TestKernel extends Kernel
{
    public function registerBundles(): array
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Coding9\ElasticApmBundle\ElasticApmBundle(),
        ];
    }
    
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
            ]);
            
            $container->loadFromExtension('elastic_apm', [
                'enabled' => true,
                'service' => [
                    'name' => 'phpunit-test',
                ],
                'server' => [
                    'url' => 'http://localhost:8200',
                ],
            ]);
        });
    }
    
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/elastic-apm-test-' . md5(__CLASS__);
    }
    
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/elastic-apm-test-logs';
    }
}