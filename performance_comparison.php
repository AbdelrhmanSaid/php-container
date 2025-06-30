THIS SHOULD BE A LINTER ERROR<?php

require_once 'vendor/autoload.php';
require_once 'src/OptimizedContainer.php';

use Redot\Container\Container;
use Redot\Container\OptimizedContainer;

class SimpleService
{
    public function __construct() {}
}

class ServiceWithDependency
{
    public function __construct(public SimpleService $service) {}
}

class ComplexService
{
    public function __construct(
        public SimpleService $simple,
        public ServiceWithDependency $withDep,
        public string $config = 'default'
    ) {}
}

interface TestInterface {}
class TestImplementation implements TestInterface {}

function benchmark(string $name, callable $callback, int $iterations = 1000): array
{
    // Warm up
    for ($i = 0; $i < 10; $i++) {
        $callback();
    }
    
    $startMemory = memory_get_usage();
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage();
    
    return [
        'name' => $name,
        'iterations' => $iterations,
        'total_time' => $endTime - $startTime,
        'avg_time' => ($endTime - $startTime) / $iterations,
        'memory_used' => $endMemory - $startMemory,
    ];
}

function compareContainers(): void
{
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "CONTAINER PERFORMANCE COMPARISON\n";
    echo str_repeat('=', 80) . "\n";

    $iterations = 1000;
    $tests = [
        'Simple Resolution',
        'Complex Dependencies', 
        'Singleton Resolution',
        'Deep Alias Chain',
        'Interface Resolution'
    ];

    $results = [];

    foreach ($tests as $test) {
        echo "Running $test test...\n";
        
        // Test original container
        $originalContainer = new Container();
        setupContainer($originalContainer);
        $originalResult = runTest($originalContainer, $test, $iterations);
        
        // Test optimized container  
        $optimizedContainer = new OptimizedContainer();
        setupOptimizedContainer($optimizedContainer);
        $optimizedResult = runTest($optimizedContainer, $test, $iterations);
        
        $improvement = (($originalResult['avg_time'] - $optimizedResult['avg_time']) / $originalResult['avg_time']) * 100;
        
        $results[] = [
            'test' => $test,
            'original' => $originalResult,
            'optimized' => $optimizedResult,
            'improvement' => $improvement
        ];
    }

    // Display results
    echo "\nRESULTS:\n";
    echo str_repeat('-', 80) . "\n";
    printf("%-20s | %-12s | %-12s | %-10s\n", "Test", "Original (ms)", "Optimized (ms)", "Improvement");
    echo str_repeat('-', 80) . "\n";

    foreach ($results as $result) {
        printf(
            "%-20s | %12.6f | %12.6f | %9.1f%%\n",
            $result['test'],
            $result['original']['avg_time'] * 1000,
            $result['optimized']['avg_time'] * 1000,
            $result['improvement']
        );
    }

    $avgImprovement = array_sum(array_column($results, 'improvement')) / count($results);
    echo str_repeat('-', 80) . "\n";
    printf("Average Improvement: %.1f%%\n", $avgImprovement);
}

function setupContainer($container): void
{
    $container->bind(SimpleService::class);
    $container->bind(ServiceWithDependency::class);
    $container->bind(ComplexService::class);
    $container->bind(TestInterface::class, TestImplementation::class);
    $container->singleton('singleton_service', SimpleService::class);
    
    // Create alias chain
    $container->alias(SimpleService::class, 'simple');
    $container->alias('simple', 'simple_alias1');
    $container->alias('simple_alias1', 'simple_alias2');
    $container->alias('simple_alias2', 'simple_alias3');
}

function setupOptimizedContainer($container): void
{
    $container->bind(SimpleService::class);
    $container->bind(ServiceWithDependency::class);
    $container->bind(ComplexService::class);
    $container->bind(TestInterface::class, TestImplementation::class);
    $container->singleton('singleton_service', SimpleService::class);
    
    // Create alias chain
    $container->alias(SimpleService::class, 'simple');
    $container->alias('simple', 'simple_alias1');
    $container->alias('simple_alias1', 'simple_alias2');
    $container->alias('simple_alias2', 'simple_alias3');
}

function runTest($container, string $testName, int $iterations): array
{
    switch ($testName) {
        case 'Simple Resolution':
            return benchmark($testName, function() use ($container) {
                return $container->make(SimpleService::class);
            }, $iterations);
            
        case 'Complex Dependencies':
            return benchmark($testName, function() use ($container) {
                return $container->make(ComplexService::class);
            }, $iterations);
            
        case 'Singleton Resolution':
            return benchmark($testName, function() use ($container) {
                return $container->make('singleton_service');
            }, $iterations);
            
        case 'Deep Alias Chain':
            return benchmark($testName, function() use ($container) {
                return $container->make('simple_alias3');
            }, $iterations);
            
        case 'Interface Resolution':
            return benchmark($testName, function() use ($container) {
                return $container->make(TestInterface::class);
            }, $iterations);
            
        default:
            throw new InvalidArgumentException("Unknown test: $testName");
    }
}

function measureMemoryUsage(): void
{
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "MEMORY USAGE COMPARISON\n";
    echo str_repeat('=', 80) . "\n";

    // Original container memory usage
    $startMemory = memory_get_usage();
    $originalContainer = new Container();
    setupContainer($originalContainer);
    
    // Resolve some services to populate caches
    for ($i = 0; $i < 100; $i++) {
        $originalContainer->make(SimpleService::class);
        $originalContainer->make(ComplexService::class);
        $originalContainer->make('simple_alias3');
    }
    $originalMemory = memory_get_usage() - $startMemory;

    // Optimized container memory usage
    $startMemory = memory_get_usage();
    $optimizedContainer = new OptimizedContainer();
    setupOptimizedContainer($optimizedContainer);
    
    // Resolve same services
    for ($i = 0; $i < 100; $i++) {
        $optimizedContainer->make(SimpleService::class);
        $optimizedContainer->make(ComplexService::class);
        $optimizedContainer->make('simple_alias3');
    }
    $optimizedMemory = memory_get_usage() - $startMemory;

    echo "Original Container Memory Usage: " . number_format($originalMemory) . " bytes\n";
    echo "Optimized Container Memory Usage: " . number_format($optimizedMemory) . " bytes\n";
    echo "Memory Difference: " . number_format($optimizedMemory - $originalMemory) . " bytes\n";
    
    if ($optimizedContainer instanceof OptimizedContainer) {
        echo "\nCache Statistics:\n";
        $stats = $optimizedContainer->getCacheStats();
        foreach ($stats as $key => $value) {
            echo ucwords(str_replace('_', ' ', $key)) . ": $value\n";
        }
    }
}

function analyzeScalability(): void
{
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "SCALABILITY ANALYSIS\n";
    echo str_repeat('=', 80) . "\n";

    $bindingCounts = [10, 50, 100, 500];
    
    foreach ($bindingCounts as $count) {
        echo "Testing with $count bindings...\n";
        
        // Original container
        $originalContainer = new Container();
        for ($i = 0; $i < $count; $i++) {
            $originalContainer->bind("service_$i", SimpleService::class);
        }
        
        $originalTime = benchmark("Original $count bindings", function() use ($originalContainer) {
            return $originalContainer->make('service_' . rand(0, 49));
        }, 100);
        
        // Optimized container
        $optimizedContainer = new OptimizedContainer();
        for ($i = 0; $i < $count; $i++) {
            $optimizedContainer->bind("service_$i", SimpleService::class);
        }
        
        $optimizedTime = benchmark("Optimized $count bindings", function() use ($optimizedContainer) {
            return $optimizedContainer->make('service_' . rand(0, 49));
        }, 100);
        
        $improvement = (($originalTime['avg_time'] - $optimizedTime['avg_time']) / $originalTime['avg_time']) * 100;
        
        printf("%d bindings: Original %.6fms, Optimized %.6fms, Improvement %.1f%%\n",
            $count,
            $originalTime['avg_time'] * 1000,
            $optimizedTime['avg_time'] * 1000,
            $improvement
        );
    }
}

// Run all comparisons
compareContainers();
measureMemoryUsage();
analyzeScalability();

echo "\n" . str_repeat('=', 80) . "\n";
echo "ANALYSIS COMPLETE\n";
echo str_repeat('=', 80) . "\n";