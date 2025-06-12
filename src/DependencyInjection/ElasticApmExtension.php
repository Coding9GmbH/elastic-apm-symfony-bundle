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


namespace Coding9\ElasticApmBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class ElasticApmExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set parameters
        $container->setParameter('elastic_apm.enabled', $config['enabled']);
        $container->setParameter('elastic_apm.interactor', $config['interactor']);
        $container->setParameter('elastic_apm.logging', $config['logging']);
        
        // Server config
        $container->setParameter('elastic_apm.server.url', $config['server']['url']);
        $container->setParameter('elastic_apm.server.secret_token', $config['server']['secret_token']);
        $container->setParameter('elastic_apm.server.api_key', $config['server']['api_key']);
        
        // OpenTracing config
        $container->setParameter('elastic_apm.opentracing.jaeger_endpoint', $config['opentracing']['jaeger_endpoint']);
        $container->setParameter('elastic_apm.opentracing.zipkin_endpoint', $config['opentracing']['zipkin_endpoint']);
        $container->setParameter('elastic_apm.opentracing.format', $config['opentracing']['format']);
        $container->setParameter('elastic_apm.opentracing.b3_propagation', $config['opentracing']['b3_propagation']);
        $container->setParameter('elastic_apm.opentracing.w3c_propagation', $config['opentracing']['w3c_propagation']);
        $container->setParameter('elastic_apm.opentracing.jaeger_propagation', $config['opentracing']['jaeger_propagation']);
        
        // Service config
        $container->setParameter('elastic_apm.service.name', $config['service']['name']);
        $container->setParameter('elastic_apm.service.version', $config['service']['version']);
        $container->setParameter('elastic_apm.service.environment', $config['service']['environment']);
        
        // Transaction config
        $container->setParameter('elastic_apm.transactions.sample_rate', $config['transactions']['sample_rate']);
        $container->setParameter('elastic_apm.transactions.max_spans', $config['transactions']['max_spans']);
        $container->setParameter('elastic_apm.transactions.naming_strategy', $config['transactions']['naming_strategy']);
        
        // Exception config
        $container->setParameter('elastic_apm.exceptions.enabled', $config['exceptions']['enabled']);
        $container->setParameter('elastic_apm.exceptions.ignored_exceptions', $config['exceptions']['ignored_exceptions']);
        $container->setParameter('elastic_apm.exceptions.unwrap_exceptions', $config['exceptions']['unwrap_exceptions']);
        
        // Memory config
        $container->setParameter('elastic_apm.memory.track_usage', $config['memory']['track_usage']);
        $container->setParameter('elastic_apm.memory.usage_label', $config['memory']['usage_label']);
        
        // Error tracking
        $container->setParameter('elastic_apm.track_deprecations', $config['track_deprecations']);
        $container->setParameter('elastic_apm.track_warnings', $config['track_warnings']);
        
        // Messaging config
        $container->setParameter('elastic_apm.messaging.enabled', $config['messaging']['enabled']);
        $container->setParameter('elastic_apm.messaging.auto_instrument_handlers', $config['messaging']['auto_instrument_handlers']);
        $container->setParameter('elastic_apm.messaging.ignored_transports', $config['messaging']['ignored_transports']);
        $container->setParameter('elastic_apm.messaging.ignored_message_classes', $config['messaging']['ignored_message_classes']);
        $container->setParameter('elastic_apm.messaging.track_message_data', $config['messaging']['track_message_data']);
        
        // RUM config
        $container->setParameter('elastic_apm.rum.enabled', $config['rum']['enabled']);
        $container->setParameter('elastic_apm.rum.expose_config_endpoint', $config['rum']['expose_config_endpoint']);
        $container->setParameter('elastic_apm.rum.service_name', $config['rum']['service_name']);
        $container->setParameter('elastic_apm.rum.server_url', $config['rum']['server_url'] ?? $config['server']['url']);

        // Load services
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        // Load interactors
        $loader->load('interactors.xml');
        
        // Load naming strategies
        $loader->load('naming_strategies.xml');

        // Conditionally load listeners based on configuration
        if ($config['enabled']) {
            $loader->load('listeners.xml');
            
            // Conditionally load memory listener
            if ($config['memory']['track_usage']) {
                $definition = $container->register('elastic_apm.listener.memory', 'App\Bundle\ElasticApmBundle\Listener\MemoryUsageListener');
                $definition->addArgument(new Reference('elastic_apm.interactor'));
                $definition->addArgument($config['memory']['usage_label']);
                $definition->addTag('kernel.event_subscriber');
            }
            
            // Conditionally disable messaging listener
            if (!$config['messaging']['enabled']) {
                $container->removeDefinition('elastic_apm.listener.messenger');
            }
        }

        // Conditionally disable RUM controller for security
        if (!$config['rum']['expose_config_endpoint']) {
            if ($container->hasDefinition('elastic_apm.controller.apm')) {
                $container->removeDefinition('elastic_apm.controller.apm');
            }
            if ($container->hasDefinition('App\\Bundle\\ElasticApmBundle\\Controller\\ApmController')) {
                $container->removeDefinition('App\\Bundle\\ElasticApmBundle\\Controller\\ApmController');
            }
        }

        // Set the interactor alias based on configuration
        $interactorId = match ($config['interactor']) {
            'blackhole' => 'elastic_apm.interactor.blackhole',
            'adaptive' => 'elastic_apm.interactor.adaptive',
            'opentracing' => 'elastic_apm.interactor.opentracing',
            default => 'elastic_apm.interactor.elastic_apm',
        };
        
        $container->setAlias('elastic_apm.interactor', $interactorId)->setPublic(true);
        $container->setAlias('App\\Bundle\\ElasticApmBundle\\Interactor\\ElasticApmInteractorInterface', $interactorId);

        // Set the naming strategy alias
        $namingStrategyId = match ($config['transactions']['naming_strategy']) {
            'controller' => 'elastic_apm.naming_strategy.controller',
            'uri' => 'elastic_apm.naming_strategy.uri',
            'service' => 'elastic_apm.naming_strategy.service',
            'message' => 'elastic_apm.naming_strategy.message',
            default => 'elastic_apm.naming_strategy.route',
        };
        
        $container->setAlias('elastic_apm.naming_strategy', $namingStrategyId)->setPublic(true);
        $container->setAlias('App\\Bundle\\ElasticApmBundle\\TransactionNamingStrategy\\TransactionNamingStrategyInterface', $namingStrategyId);

        // Configure logger if enabled
        if ($config['logging']) {
            $this->configureLogging($container);
        }
    }

    private function configureLogging(ContainerBuilder $container): void
    {
        // Configure services to use logger
        $serviceIds = [
            'elastic_apm.interactor.elastic_apm',
            'elastic_apm.interactor.adaptive',
        ];

        foreach ($serviceIds as $serviceId) {
            if ($container->hasDefinition($serviceId)) {
                $definition = $container->getDefinition($serviceId);
                $definition->addMethodCall('setLogger', [new Reference('logger')]);
                $definition->addTag('monolog.logger', ['channel' => 'elastic_apm']);
            }
        }
    }

    public function getAlias(): string
    {
        return 'elastic_apm';
    }
}