<?php

require_once 'vendor/autoload.php';

use Redot\Container\Container;

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

// Interface for testing
interface TestInterface {}
class TestImplementation implements TestInterface {}

function benchmark(string $name, callable $callback, int $iterations = 1000): array
{
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
        'avg_memory' => ($endMemory - $startMemory) / $iterations
    ];
}

function formatResults(array $results): void
{
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "PERFORMANCE ANALYSIS RESULTS\n";
    echo str_repeat('=', 80) . "\n";
    
    foreach ($results as $result) {
        echo sprintf(
            "%-30s | %d iterations | %.4fms total | %.6fms avg | %d bytes memory\n",
            $result['name'],
            $result['iterations'],
            $result['total_time'] * 1000,
            $result['avg_time'] * 1000,
            $result['memory_used']
        );
    }
    echo str_repeat('=', 80) . "\n";
}

// Initialize container
$container = new Container();

// Performance tests
$results = [];

// Test 1: Container creation
$results[] = benchmark('Container Creation', function() {
    return new Container();
}, 10000);

// Test 2: Simple binding and resolution
$container->bind(SimpleService::class);
$results[] = benchmark('Simple Resolution', function() use ($container) {
    return $container->make(SimpleService::class);
}, 1000);

// Test 3: Singleton resolution (should be cached)
$container->singleton('singleton_service', SimpleService::class);
$results[] = benchmark('Singleton Resolution', function() use ($container) {
    return $container->make('singleton_service');
}, 1000);

// Test 4: Complex dependency resolution
$container->bind(ServiceWithDependency::class);
$container->bind(ComplexService::class);
$results[] = benchmark('Complex Dependencies', function() use ($container) {
    return $container->make(ComplexService::class);
}, 1000);

// Test 5: Interface binding
$container->bind(TestInterface::class, TestImplementation::class);
$results[] = benchmark('Interface Resolution', function() use ($container) {
    return $container->make(TestInterface::class);
}, 1000);

// Test 6: Closure binding
$container->bind('closure_service', function() {
    return new SimpleService();
});
$results[] = benchmark('Closure Resolution', function() use ($container) {
    return $container->make('closure_service');
}, 1000);

// Test 7: Alias resolution
$container->alias(SimpleService::class, 'simple');
$results[] = benchmark('Alias Resolution', function() use ($container) {
    return $container->make('simple');
}, 1000);

// Test 8: Method calling
$results[] = benchmark('Method Calling', function() use ($container) {
    return $container->call([SimpleService::class, '__construct']);
}, 1000);

// Test 9: Deep alias chain (potential bottleneck)
$container->alias('simple', 'simple_alias1');
$container->alias('simple_alias1', 'simple_alias2');
$container->alias('simple_alias2', 'simple_alias3');
$results[] = benchmark('Deep Alias Chain', function() use ($container) {
    return $container->make('simple_alias3');
}, 1000);

// Test 10: Memory usage with many bindings
$container2 = new Container();
for ($i = 0; $i < 100; $i++) {
    $container2->bind("service_$i", SimpleService::class);
}
$results[] = benchmark('Many Bindings Resolution', function() use ($container2) {
    return $container2->make('service_50');
}, 1000);

formatResults($results);

// Memory analysis
echo "\nMEMORY ANALYSIS:\n";
echo str_repeat('-', 40) . "\n";
echo "Container with 100 bindings: " . memory_get_usage() . " bytes\n";

$emptyContainer = new Container();
echo "Empty container: " . memory_get_usage() . " bytes\n";

// Reflection caching analysis
echo "\nREFLECTION ANALYSIS:\n";
echo str_repeat('-', 40) . "\n";

$reflectionStart = microtime(true);
for ($i = 0; $i < 100; $i++) {
    new ReflectionClass(SimpleService::class);
}
$reflectionTime = microtime(true) - $reflectionStart;

$containerStart = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $container->make(SimpleService::class);
}
$containerTime = microtime(true) - $containerStart;

echo "100 ReflectionClass creations: " . ($reflectionTime * 1000) . "ms\n";
echo "100 Container resolutions: " . ($containerTime * 1000) . "ms\n";
echo "Container overhead: " . (($containerTime - $reflectionTime) * 1000) . "ms\n";

// Identify bottlenecks
echo "\nBOTTLENECK ANALYSIS:\n";
echo str_repeat('-', 40) . "\n";

$slowest = array_reduce($results, function($carry, $item) {
    return $carry === null || $item['avg_time'] > $carry['avg_time'] ? $item : $carry;
});

$fastest = array_reduce($results, function($carry, $item) {
    return $carry === null || $item['avg_time'] < $carry['avg_time'] ? $item : $carry;
});

echo "Slowest operation: {$slowest['name']} - " . ($slowest['avg_time'] * 1000) . "ms avg\n";
echo "Fastest operation: {$fastest['name']} - " . ($fastest['avg_time'] * 1000) . "ms avg\n";
echo "Performance ratio: " . round($slowest['avg_time'] / $fastest['avg_time'], 2) . "x\n";

echo "\nRECOMMENDATIONS:\n";
echo str_repeat('-', 40) . "\n";

// Analyze results and provide recommendations
$complexResults = array_filter($results, fn($r) => $r['name'] === 'Complex Dependencies');
$simpleResults = array_filter($results, fn($r) => $r['name'] === 'Simple Resolution');
$singletonResults = array_filter($results, fn($r) => $r['name'] === 'Singleton Resolution');
$aliasResults = array_filter($results, fn($r) => $r['name'] === 'Deep Alias Chain');

if (!empty($complexResults) && !empty($simpleResults)) {
    $avgComplexTime = array_values($complexResults)[0]['avg_time'];
    $avgSimpleTime = array_values($simpleResults)[0]['avg_time'];
    
    if ($avgSimpleTime > 0 && $avgComplexTime / $avgSimpleTime > 5) {
        echo "1. Complex dependency resolution is " . round($avgComplexTime / $avgSimpleTime, 1) . "x slower than simple resolution\n";
        echo "   Consider caching reflection data or using precompiled dependency maps\n";
    }
}

if (!empty($singletonResults) && !empty($simpleResults)) {
    $singletonTime = array_values($singletonResults)[0]['avg_time'];
    $avgSimpleTime = array_values($simpleResults)[0]['avg_time'];
    
    if ($avgSimpleTime > 0 && $singletonTime / $avgSimpleTime > 1.5) {
        echo "2. Singleton resolution not as fast as expected - cache lookup may be inefficient\n";
    }
}

if (!empty($aliasResults) && !empty($simpleResults)) {
    $aliasTime = array_values($aliasResults)[0]['avg_time'];
    $avgSimpleTime = array_values($simpleResults)[0]['avg_time'];
    
    if ($avgSimpleTime > 0 && $aliasTime / $avgSimpleTime > 3) {
        echo "3. Deep alias chains cause significant performance degradation\n";
        echo "   Consider flattening alias chains or caching resolved aliases\n";
    }
}

echo "\nEND OF ANALYSIS\n";