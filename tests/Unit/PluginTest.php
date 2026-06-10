<?php

use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info as TelemetryInfo;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\TestData\TestDataCollection;
use SchenkeIo\TestOutputFormatter\Pest\Cache;
use SchenkeIo\TestOutputFormatter\Pest\Git;
use SchenkeIo\TestOutputFormatter\Pest\Options;
use SchenkeIo\TestOutputFormatter\Plugin;

function createPlugin(): Plugin
{
    $plugin = (new ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
    setProp($plugin, 'options', new Options);

    return $plugin;
}

function setProp($obj, $propName, $value, $class = null)
{
    $class = $class ?? get_class($obj);
    $prop = new ReflectionProperty($class, $propName);
    $prop->setAccessible(true);
    $prop->setValue($obj, $value);
}

function createTestMethod(string $file, string $name, string $className = 'MyClass'): TestMethod
{
    $reflection = new ReflectionClass(TestMethod::class);
    $test = $reflection->newInstanceWithoutConstructor();

    setProp($test, 'file', $file, Test::class);
    setProp($test, 'className', $className, TestMethod::class);
    setProp($test, 'methodName', $name, TestMethod::class);
    setProp($test, 'line', 1, TestMethod::class);
    setProp($test, 'testData', TestDataCollection::fromArray([]), TestMethod::class);

    return $test;
}

function createTelemetryInfo(HRTime $time): TelemetryInfo
{
    $reflection = new ReflectionClass(TelemetryInfo::class);
    $info = $reflection->newInstanceWithoutConstructor();

    $snapshotReflection = new ReflectionClass(Snapshot::class);
    $snapshot = $snapshotReflection->newInstanceWithoutConstructor();
    setProp($snapshot, 'time', $time, Snapshot::class);

    setProp($info, 'current', $snapshot, TelemetryInfo::class);

    return $info;
}

function createPrepared(TestMethod $test, HRTime $time): Prepared
{
    $reflection = new ReflectionClass(Prepared::class);
    $prep = $reflection->newInstanceWithoutConstructor();

    setProp($prep, 'test', $test, Prepared::class);
    setProp($prep, 'telemetryInfo', createTelemetryInfo($time), Prepared::class);

    return $prep;
}

function createFinished(TestMethod $test, HRTime $time): Finished
{
    $reflection = new ReflectionClass(Finished::class);
    $fin = $reflection->newInstanceWithoutConstructor();

    setProp($fin, 'test', $test, Finished::class);
    setProp($fin, 'telemetryInfo', createTelemetryInfo($time), Finished::class);

    return $fin;
}

it('handles arguments correctly', function () {
    $plugin = createPlugin();

    $args = ['--failed-files-only', '--slowest=5', '--over=100', '--format=json', '--under=50'];
    $newArgs = $plugin->handleArguments($args);

    expect($newArgs)->toContain('--coverage');
    // The custom flags should be removed from the arguments passed to Pest/PHPUnit
    expect($newArgs)->not->toContain('--failed-files-only');
    expect($newArgs)->not->toContain('--slowest=5');
    expect($newArgs)->not->toContain('--over=100');
    expect($newArgs)->not->toContain('--format=json');
    expect($newArgs)->not->toContain('--under=50');
});

it('handles --under with space correctly', function () {
    $plugin = createPlugin();

    $args = ['--under', '60', 'testfile.php'];
    $newArgs = $plugin->handleArguments($args);

    expect($newArgs)->toContain('--coverage');
    expect($newArgs)->toContain('testfile.php');
    expect($newArgs)->not->toContain('--under');
    expect($newArgs)->not->toContain('60');
});

it('does not add --coverage if already present', function () {
    $plugin = createPlugin();

    $args = ['--coverage', '--under=50'];
    $newArgs = $plugin->handleArguments($args);

    $coverageCount = count(array_filter($newArgs, fn ($a) => $a === '--coverage'));
    expect($coverageCount)->toBe(1);
});

it('handles --under without value gracefully', function () {
    $plugin = createPlugin();

    $args = ['--under'];
    $newArgs = $plugin->handleArguments($args);

    expect($newArgs)->not->toContain('--coverage');
});

it('normalizes failed file paths', function () {
    $plugin = createPlugin();
    $cwd = getcwd();
    $absPath = $cwd.DIRECTORY_SEPARATOR.'tests/MyTest.php';

    $test = createTestMethod($absPath, 'testSomething');

    $plugin->addFailedFile($test);

    $plugin->handleArguments(['--failed-files-only']);

    ob_start();
    $plugin->addOutput(1);
    $output = ob_get_clean();

    expect($output)->toContain('tests/MyTest.php');
});

it('handles evald code paths in failed files', function () {
    $plugin = createPlugin();
    $evalPath = "/path/to/file.php(10) : eval()'d code";

    $test = createTestMethod($evalPath, 'testSomething');

    $plugin->addFailedFile($test);

    $plugin->handleArguments(['--failed-files-only']);
    ob_start();
    $plugin->addOutput(1);
    $output = ob_get_clean();

    expect($output)->toContain('/path/to/file.php');
});

it('records and sorts timing results correctly', function () {
    $plugin = createPlugin();
    $plugin->handleArguments(['--slowest=10']);

    $test1 = createTestMethod('FileB.php', 'testB');
    $test2 = createTestMethod('FileA.php', 'testA');

    $time0 = HRTime::fromSecondsAndNanoseconds(0, 0);
    $time1 = HRTime::fromSecondsAndNanoseconds(0, 100000000); // 100ms
    $time2 = HRTime::fromSecondsAndNanoseconds(0, 200000000); // 200ms

    $prep1 = createPrepared($test1, $time0);
    $fin1 = createFinished($test1, $time2); // 200ms

    $prep2 = createPrepared($test2, $time0);
    $fin2 = createFinished($test2, $time1); // 100ms

    $plugin->recordTestStart($prep1);
    $plugin->recordTestEnd($fin1);
    $plugin->recordTestStart($prep2);
    $plugin->recordTestEnd($fin2);

    ob_start();
    $plugin->addOutput(0);
    $output = ob_get_clean();

    // Sorting should be alphabetical by file: FileA, then FileB
    $posA = strpos($output, 'FileA.php');
    $posB = strpos($output, 'FileB.php');
    expect($posA)->toBeLessThan($posB);
    expect($output)->toContain('FileA.php: testA took 100ms');
    expect($output)->toContain('FileB.php: testB took 200ms');
});

it('outputs JSON format correctly with all components', function () {
    $plugin = createPlugin();
    $plugin->handleArguments(['--format=json', '--slowest=10']);

    // Add failed file
    $testFail = createTestMethod('Fail.php', 'testFail');
    $plugin->addFailedFile($testFail);

    // Add timing
    $testTime = createTestMethod('Time.php', 'testTime');

    $time0 = HRTime::fromSecondsAndNanoseconds(0, 0);
    $time1 = HRTime::fromSecondsAndNanoseconds(0, 150000000); // 150ms

    $prep = createPrepared($testTime, $time0);
    $fin = createFinished($testTime, $time1);

    $plugin->recordTestStart($prep);
    $plugin->recordTestEnd($fin);

    ob_start();
    $plugin->addOutput(1);
    $output = ob_get_clean();

    $data = json_decode($output, true);
    expect($data)->toHaveKeys(['exitCode', 'failedFiles', 'underCovered', 'timing', 'coverageMap']);
    expect($data['exitCode'])->toBe(1);
    expect($data['failedFiles'])->toContain('Fail.php');
    expect($data['timing'][0]['file'])->toBe('Time.php');
    expect($data['timing'][0]['ms'])->toEqual(150.0);
});

it('uses coverage map to select tests when source files change', function () {
    $cacheDir = sys_get_temp_dir().'/pest_cache_'.uniqid();
    if (! is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }

    $plugin = createPlugin();
    setProp($plugin, 'options', new Options(changed: true, cacheDir: $cacheDir));

    // Mock Cache - write the map directly
    $cache = new Cache($cacheDir);
    $coverageMap = [
        'src/Source.php' => ['tests/TestA.php'],
    ];
    $cache->write('source-to-tests.json', $coverageMap);

    // Mock Git
    $git = Mockery::mock(Git::class);
    $git->shouldReceive('getChangedFiles')->andReturn(['src/Source.php']);
    Plugin::setTestGit($git);

    // Create fake test file
    $testFile = getcwd().'/tests/TestA.php';
    if (! is_dir(dirname($testFile))) {
        mkdir(dirname($testFile), 0777, true);
    }
    touch($testFile);

    $args = ['pest', '--changed', '--cache-dir='.$cacheDir];
    $newArgs = $plugin->handleArguments($args);

    expect($newArgs)->toContain('tests/TestA.php');
    expect($newArgs)->not->toContain('src/Source.php');

    // Clean up
    unlink($testFile);
    array_map('unlink', glob("$cacheDir/*"));
    rmdir($cacheDir);
    Plugin::setTestGit(null);
});

it('sorts timing results by test name when files are identical', function () {
    $plugin = createPlugin();
    $plugin->handleArguments(['--slowest=10']);

    $test1 = createTestMethod('SameFile.php', 'testB');
    $test2 = createTestMethod('SameFile.php', 'testA');

    $time0 = HRTime::fromSecondsAndNanoseconds(0, 0);
    $time1 = HRTime::fromSecondsAndNanoseconds(0, 100000000);

    $plugin->recordTestStart(createPrepared($test1, $time0));
    $plugin->recordTestEnd(createFinished($test1, $time1));

    $plugin->recordTestStart(createPrepared($test2, $time0));
    $plugin->recordTestEnd(createFinished($test2, $time1));

    ob_start();
    $plugin->addOutput(0);
    $output = ob_get_clean();

    $posA = strpos($output, 'testA');
    $posB = strpos($output, 'testB');
    expect($posA)->toBeLessThan($posB);
});

it('registers subscribers in constructor and they call plugin methods', function () {
    $facade = Mockery::mock();
    $subscribers = [];
    $facade->shouldReceive('registerSubscriber')->times(3)->andReturnUsing(function ($sub) use (&$subscribers) {
        $subscribers[] = $sub;
    });

    Plugin::setTestEventFacade($facade);
    $plugin = new Plugin;
    Plugin::setTestEventFacade(null); // Cleanup

    $plugin->handleArguments(['--failed-files-only', '--slowest=10']);

    expect($subscribers)->toHaveCount(3);

    // Test FailedSubscriber
    $failedEvent = (new ReflectionClass(Failed::class))->newInstanceWithoutConstructor();
    $test = createTestMethod('fail.php', 'testFail');
    setProp($failedEvent, 'test', $test, Failed::class);
    $subscribers[0]->notify($failedEvent);

    // Test PreparedSubscriber
    $preparedEvent = (new ReflectionClass(Prepared::class))->newInstanceWithoutConstructor();
    setProp($preparedEvent, 'test', $test, Prepared::class);
    setProp($preparedEvent, 'telemetryInfo', createTelemetryInfo(HRTime::fromSecondsAndNanoseconds(0, 0)), Prepared::class);
    $subscribers[1]->notify($preparedEvent);

    // Test FinishedSubscriber
    $finishedEvent = (new ReflectionClass(Finished::class))->newInstanceWithoutConstructor();
    setProp($finishedEvent, 'test', $test, Finished::class);
    setProp($finishedEvent, 'telemetryInfo', createTelemetryInfo(HRTime::fromSecondsAndNanoseconds(0, 100000000)), Finished::class);
    $subscribers[2]->notify($finishedEvent);

    // Verify that the plugin recorded these
    ob_start();
    $plugin->addOutput(1);
    $output = ob_get_clean();

    expect($output)->toContain('fail.php');
    expect($output)->toContain('fail.php: testFail took 100ms');
});
