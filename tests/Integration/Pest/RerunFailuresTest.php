<?php

namespace Tests\Integration;

use SchenkeIo\TestOutputFormatter\Pest\Cache;
use Symfony\Component\Process\Process;

it('reruns only failed files when --rerun-failures is used', function () {
    $cacheDir = getcwd().'/.pest_cache_test';
    if (! is_dir($cacheDir)) {
        mkdir($cacheDir);
    }
    $cache = new Cache($cacheDir);

    // 1. Simulate a previous run that failed
    $failedFile = 'tests/Fixtures/tests/FailingTest.php';
    $cache->write('failed-files.json', [$failedFile]);

    // 2. Run Pest with --rerun-failures
    // We must pass some directory or it will run everything by default if handleArguments returns something else
    // But if handleArguments returns the failed files, Pest will use them.
    $process = new Process([
        'vendor/bin/pest',
        '--rerun-failures',
        '--cache-dir=.pest_cache_test',
        '--colors=never',
    ]);

    $process->run();
    $output = $process->getOutput();

    // ASSERTIONS:
    // It should have run FailingTest.php
    expect($output)->toContain('FailingTest');
    // It should NOT have run PassingTest.php (because we didn't include it in failed-files.json)
    expect($output)->not->toContain('PassingTest');

    // Clean up
    array_map('unlink', glob("$cacheDir/*"));
    rmdir($cacheDir);
});
