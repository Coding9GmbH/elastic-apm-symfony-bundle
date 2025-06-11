<?php

require_once 'vendor/autoload.php';

use ElasticApmBundle\Interactor\OpenTracingInteractor;
use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use ElasticApmBundle\TransactionNamingStrategy\RouteNamingStrategy;

echo "Testing OpenTracingInteractor interface compliance...\n";

// Check if class implements interface
$reflection = new ReflectionClass(OpenTracingInteractor::class);
if (!$reflection->implementsInterface(ElasticApmInteractorInterface::class)) {
    echo "❌ OpenTracingInteractor does not implement ElasticApmInteractorInterface\n";
    exit(1);
}

echo "✅ OpenTracingInteractor implements ElasticApmInteractorInterface\n";

// Check all interface methods exist and have correct signatures
$interface = new ReflectionClass(ElasticApmInteractorInterface::class);
$errors = [];

foreach ($interface->getMethods() as $interfaceMethod) {
    $methodName = $interfaceMethod->getName();
    
    if (!$reflection->hasMethod($methodName)) {
        $errors[] = "Missing method: $methodName";
        continue;
    }
    
    $implMethod = $reflection->getMethod($methodName);
    
    // Check parameter count
    if ($interfaceMethod->getNumberOfParameters() !== $implMethod->getNumberOfParameters()) {
        $errors[] = "Parameter count mismatch for $methodName";
        continue;
    }
    
    // Check parameter types
    $interfaceParams = $interfaceMethod->getParameters();
    $implParams = $implMethod->getParameters();
    
    for ($i = 0; $i < count($interfaceParams); $i++) {
        $interfaceParam = $interfaceParams[$i];
        $implParam = $implParams[$i];
        
        $interfaceType = $interfaceParam->getType();
        $implType = $implParam->getType();
        
        if ($interfaceType && $implType) {
            if ($interfaceType->getName() !== $implType->getName()) {
                $errors[] = "Parameter type mismatch for $methodName parameter " . $interfaceParam->getName();
            }
        } elseif ($interfaceType xor $implType) {
            $errors[] = "Parameter type presence mismatch for $methodName parameter " . $interfaceParam->getName();
        }
    }
    
    // Check return type
    $interfaceReturnType = $interfaceMethod->getReturnType();
    $implReturnType = $implMethod->getReturnType();
    
    if ($interfaceReturnType && $implReturnType) {
        if ($interfaceReturnType->getName() !== $implReturnType->getName()) {
            $errors[] = "Return type mismatch for $methodName";
        }
    } elseif ($interfaceReturnType xor $implReturnType) {
        $errors[] = "Return type presence mismatch for $methodName";
    }
}

if (empty($errors)) {
    echo "✅ All interface methods are correctly implemented\n";
    
    // Try to instantiate the class
    try {
        $config = [
            'enabled' => true,
            'server' => ['url' => 'http://localhost:14268'],
            'service' => ['name' => 'test', 'version' => '1.0', 'environment' => 'test']
        ];
        $namingStrategy = new RouteNamingStrategy();
        $interactor = new OpenTracingInteractor($config, $namingStrategy);
        
        echo "✅ OpenTracingInteractor can be instantiated successfully\n";
        echo "✅ All tests passed - OpenTracingInteractor is fully compliant\n";
    } catch (Throwable $e) {
        echo "❌ Failed to instantiate OpenTracingInteractor: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "❌ Found " . count($errors) . " interface compliance errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}