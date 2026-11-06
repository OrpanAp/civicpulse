<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CivicPulse\Framework\Container\Container;
use CivicPulse\Framework\Container\ContainerException;

function containerCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    echo "PASSED: {$message}" . PHP_EOL;
}

// Test classes and contracts.

interface TestLoggerInterface {}

final class TestLogger implements TestLoggerInterface {}

final class TestRepository {}

final class TestService
{
    public function __construct(
        public readonly TestRepository $repository
    ) {}
}

final class TestController
{
    public function __construct(
        public readonly TestService $service
    ) {}
}

final class CircularA
{
    public function __construct(CircularB $dependency) {}
}

final class CircularB
{
    public function __construct(CircularA $dependency) {}
}

final class ScalarDependency
{
    public function __construct(
        public readonly string $name
    ) {}
}

final class DefaultDependency
{
    public function __construct(
        public readonly string $name = 'CivicPulse'
    ) {}
}

interface TestCacheInterface {}

interface TestStorageInterface {}

final class TestStorage implements
    TestCacheInterface,
    TestStorageInterface {}

final class UnionDependency
{
    public function __construct(
        TestLoggerInterface|TestRepository $dependency
    ) {}
}

final class IntersectionDependency
{
    public function __construct(
        TestCacheInterface&TestStorageInterface $dependency
    ) {}
}

final class NullableClassDependency
{
    public function __construct(
        public readonly ?TestRepository $repository
    ) {}
}

echo "Testing service container..." . PHP_EOL;

$container = new Container();

// 1. Transient binding
$container->bind(
    TestLoggerInterface::class,
    TestLogger::class
);

$loggerOne = $container->make(TestLoggerInterface::class);
$loggerTwo = $container->make(TestLoggerInterface::class);

containerCheck(
    $loggerOne instanceof TestLogger,
    'Interface resolves to its concrete implementation'
);

containerCheck(
    $loggerOne !== $loggerTwo,
    'Transient bindings create separate instances'
);

// 2. Existing instance
$existingRepository = new TestRepository();

$container->instance(
    TestRepository::class,
    $existingRepository
);

containerCheck(
    $container->make(TestRepository::class) === $existingRepository,
    'Registered instances are returned directly'
);

// 3. Singleton binding
$container->singleton(
    TestService::class
);

$serviceOne = $container->make(TestService::class);
$serviceTwo = $container->make(TestService::class);

containerCheck(
    $serviceOne === $serviceTwo,
    'Singleton bindings reuse the same instance'
);

containerCheck(
    $container->make(TestRepository::class) === $existingRepository,
    'Registered instances are returned directly'
);

// 4. Automatic constructor injection
$controller = $container->make(TestController::class);

containerCheck(
    $controller instanceof TestController,
    'Controller is automatically constructed'
);

containerCheck(
    $controller->service instanceof TestService,
    'First-level dependency is injected'
);

containerCheck(
    $controller->service->repository === $existingRepository,
    'Nested dependency uses the registered instance'
);

// 5. Closure factory
$container->bind(
    'test.factory',
    static fn(Container $container): TestRepository =>
    $container->make(TestRepository::class)
);

containerCheck(
    $container->make('test.factory') === $existingRepository,
    'Closure factories can resolve container dependencies'
);

// 6. Constructor default values
$defaultDependency = $container->make(DefaultDependency::class);

containerCheck(
    $defaultDependency->name === 'CivicPulse',
    'Constructor default values are respected'
);

// 7. Unresolvable scalar dependency
$scalarRejected = false;

try {
    $container->make(ScalarDependency::class);
} catch (ContainerException) {
    $scalarRejected = true;
}

containerCheck(
    $scalarRejected,
    'Unconfigured scalar dependencies are rejected'
);

// 8. Circular dependency detection
$circularRejected = false;
$circularMessage = '';

try {
    $container->make(CircularA::class);
} catch (ContainerException $exception) {
    $circularRejected = true;
    $circularMessage = $exception->getMessage();
}

containerCheck(
    $circularRejected,
    'Circular dependencies are detected'
);

containerCheck(
    str_contains($circularMessage, 'CircularA')
        && str_contains($circularMessage, 'CircularB'),
    'Circular dependency error identifies the resolution chain'
);


// 9. Singleton closure factory

$factoryCalls = 0;

$container->singleton(
    'test.singleton.factory',
    static function (Container $container) use (&$factoryCalls): object {
        $factoryCalls++;

        return new stdClass();
    }
);

$factoryOne = $container->make('test.singleton.factory');
$factoryTwo = $container->make('test.singleton.factory');

containerCheck(
    $factoryOne === $factoryTwo && $factoryCalls === 1,
    'Singleton closure factories execute only once'
);

// 10. Failed resolution cleanup

$attempts = 0;

$container->bind(
    'test.retry.factory',
    static function (Container $container) use (&$attempts): object {
        $attempts++;

        if ($attempts === 1) {
            throw new RuntimeException('Intentional factory failure.');
        }

        return new stdClass();
    }
);

try {
    $container->make('test.retry.factory');
} catch (RuntimeException) {
    // Expected first attempt to fail.
}

$retryResult = $container->make('test.retry.factory');

containerCheck(
    $retryResult instanceof stdClass && $attempts === 2,
    'Failed resolution cleans up the container state for retry'
);

// 11. Circular service aliases

$container->bind(
    'test.alias.a',
    'test.alias.b'
);

$container->bind(
    'test.alias.b',
    'test.alias.a'
);

$aliasCycleRejected = false;
$aliasCycleMessage = '';

try {
    $container->make('test.alias.a');
} catch (ContainerException $exception) {
    $aliasCycleRejected = true;
    $aliasCycleMessage = $exception->getMessage();
}

containerCheck(
    $aliasCycleRejected
        && str_contains($aliasCycleMessage, 'Circular dependency detected'),
    'Circular service aliases are detected'
);

containerCheck(
    str_contains($aliasCycleMessage, 'test.alias.a')
        && str_contains($aliasCycleMessage, 'test.alias.b'),
    'Circular alias error identifies the resolution chain'
);

// 12. Singleton rebinding

$replacementService = new TestService($existingRepository);

$container->singleton(
    TestService::class,
    static fn(Container $container): TestService => $replacementService
);

$reboundService = $container->make(TestService::class);

containerCheck(
    $reboundService === $replacementService
        && $reboundService !== $serviceOne,
    'Rebinding replaces the previously cached singleton'
);

/**
 * 13. Union dependencies are rejected.
 */
$unionRejected = false;

try {
    $container->make(UnionDependency::class);
} catch (ContainerException) {
    $unionRejected = true;
}

containerCheck(
    $unionRejected,
    'Unsupported union dependencies are rejected'
);

/**
 * 14. Intersection dependencies are rejected.
 */
$intersectionRejected = false;

try {
    $container->make(IntersectionDependency::class);
} catch (ContainerException) {
    $intersectionRejected = true;
}

containerCheck(
    $intersectionRejected,
    'Unsupported intersection dependencies are rejected'
);

/**
 * 15. Nullable class dependencies.
 */
$nullableDependency = $container->make(
    NullableClassDependency::class
);

containerCheck(
    $nullableDependency->repository === $existingRepository,
    'Nullable class dependencies are automatically resolved when available'
);

/**
 * 16. Invalid factory return value.
 */
$container->bind(
    'test.invalid.factory',
    static fn(Container $container): mixed => 'not-an-object'
);

$invalidFactoryRejected = false;

try {
    $container->make('test.invalid.factory');
} catch (ContainerException) {
    $invalidFactoryRejected = true;
}

containerCheck(
    $invalidFactoryRejected,
    'Factories returning non-objects are rejected'
);

/**
 * 17. Unbound interface and abstract class.
 */
abstract class TestAbstractService {}

$unboundInterfaceRejected = false;

try {
    $container->make(TestCacheInterface::class);
} catch (ContainerException) {
    $unboundInterfaceRejected = true;
}

containerCheck(
    $unboundInterfaceRejected,
    'Unbound interfaces are rejected'
);

$abstractClassRejected = false;

try {
    $container->make(TestAbstractService::class);
} catch (ContainerException) {
    $abstractClassRejected = true;
}

containerCheck(
    $abstractClassRejected,
    'Unbound abstract classes are rejected'
);

// 9. Invalid service
$missingServiceRejected = false;

try {
    $container->make('CivicPulse\\Missing\\Service');
} catch (ContainerException) {
    $missingServiceRejected = true;
}

containerCheck(
    $missingServiceRejected,
    'Missing service classes are rejected'
);

// 10. Empty service identifier
$emptyIdentifierRejected = false;

try {
    $container->make(' ');
} catch (ContainerException) {
    $emptyIdentifierRejected = true;
}

containerCheck(
    $emptyIdentifierRejected,
    'Empty service identifiers are rejected'
);

echo PHP_EOL . "All service container tests passed." . PHP_EOL;
