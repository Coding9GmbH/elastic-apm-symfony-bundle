<?php

namespace ElasticApmBundle\Tests\Unit\Interactor;

use ElasticApmBundle\Interactor\AdaptiveInteractor;
use ElasticApmBundle\Interactor\BlackholeInteractor;
use ElasticApmBundle\Interactor\ElasticApmInteractor;
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use ElasticApmBundle\Interactor\OpenTracingInteractor;
use PHPUnit\Framework\TestCase;

/**
 * Test that all interactor implementations properly implement the interface
 */
class InterfaceComplianceTest extends TestCase
{
    /**
     * @dataProvider interactorImplementations
     */
    public function testImplementsInterface(string $className): void
    {
        $reflection = new \ReflectionClass($className);
        $this->assertTrue(
            $reflection->implementsInterface(ElasticApmInteractorInterface::class),
            "$className must implement ElasticApmInteractorInterface"
        );
    }

    /**
     * @dataProvider interactorImplementations
     */
    public function testAllInterfaceMethodsAreImplemented(string $className): void
    {
        $interface = new \ReflectionClass(ElasticApmInteractorInterface::class);
        $implementation = new \ReflectionClass($className);

        foreach ($interface->getMethods() as $interfaceMethod) {
            $methodName = $interfaceMethod->getName();
            
            $this->assertTrue(
                $implementation->hasMethod($methodName),
                "$className must implement method: $methodName"
            );

            $implMethod = $implementation->getMethod($methodName);
            
            // Check method is public
            $this->assertTrue(
                $implMethod->isPublic(),
                "$className::$methodName must be public"
            );

            // Check return type matches
            $interfaceReturnType = $interfaceMethod->getReturnType();
            $implReturnType = $implMethod->getReturnType();
            
            if ($interfaceReturnType !== null) {
                $this->assertNotNull(
                    $implReturnType,
                    "$className::$methodName must have a return type"
                );
                
                $this->assertEquals(
                    $interfaceReturnType->getName(),
                    $implReturnType->getName(),
                    "$className::$methodName return type must match interface"
                );
            }

            // Check parameter count matches
            $this->assertEquals(
                $interfaceMethod->getNumberOfParameters(),
                $implMethod->getNumberOfParameters(),
                "$className::$methodName parameter count must match interface"
            );

            // Check each parameter
            $interfaceParams = $interfaceMethod->getParameters();
            $implParams = $implMethod->getParameters();
            
            foreach ($interfaceParams as $index => $interfaceParam) {
                $implParam = $implParams[$index];
                
                // Check parameter name
                $this->assertEquals(
                    $interfaceParam->getName(),
                    $implParam->getName(),
                    "$className::$methodName parameter $index name must match interface"
                );
                
                // Check parameter type
                $interfaceType = $interfaceParam->getType();
                $implType = $implParam->getType();
                
                if ($interfaceType !== null) {
                    $this->assertNotNull(
                        $implType,
                        "$className::$methodName parameter {$interfaceParam->getName()} must have a type"
                    );
                    
                    $this->assertEquals(
                        $interfaceType->getName(),
                        $implType->getName(),
                        "$className::$methodName parameter {$interfaceParam->getName()} type must match interface"
                    );
                }
                
                // Check if parameter is optional
                $this->assertEquals(
                    $interfaceParam->isOptional(),
                    $implParam->isOptional(),
                    "$className::$methodName parameter {$interfaceParam->getName()} optional status must match interface"
                );
            }
        }
    }

    public function interactorImplementations(): array
    {
        return [
            'ElasticApmInteractor' => [ElasticApmInteractor::class],
            'OpenTracingInteractor' => [OpenTracingInteractor::class],
            'BlackholeInteractor' => [BlackholeInteractor::class],
            'AdaptiveInteractor' => [AdaptiveInteractor::class],
        ];
    }

    public function testOpenTracingInteractorSpecificMethods(): void
    {
        // Test that OpenTracingInteractor has all the legacy methods that might be called
        $reflection = new \ReflectionClass(OpenTracingInteractor::class);
        
        $legacyMethods = [
            'beginTransaction',
            'beginCurrentTransaction',
            'endCurrentTransaction',
            'beginCurrentSpan',
            'endCurrentSpan',
            'setTransactionContext',
            'setTransactionLabels',
            'addTransactionLabel',
            'getCurrentTraceId',
            'getCurrentTransactionId',
            'startRequestTransaction',
            'endRequestTransaction',
        ];
        
        foreach ($legacyMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "OpenTracingInteractor must have legacy method: $method"
            );
        }
    }
}