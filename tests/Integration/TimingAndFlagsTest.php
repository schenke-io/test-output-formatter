<?php

use Symfony\Component\Process\Process;

it('outputs timing in text format when --slowest is used', function () {
    $process = new Process([
        'vendor/bin/pest',
        'tests/Fixtures/tests/PassingTest.php',
        '--slowest=10',
        '--colors=never',
        '--quiet',
    ]);

    $process->run();
    $output = trim($process->getOutput());

    expect($output)->toContain('CUSTOM_TIMING_START');
    expect($output)->toContain('tests/Fixtures/tests/PassingTest.php: test_this_test_passes_completely took');
    expect($output)->toContain('CUSTOM_TIMING_END');
});

it('outputs timing in JSON format when --format=json and --slowest are used', function () {
    $process = new Process([
        'vendor/bin/pest',
        'tests/Fixtures/tests/PassingTest.php',
        '--slowest=10',
        '--format=json',
        '--colors=never',
        '--quiet',
    ]);

    $process->run();
    $output = trim($process->getOutput());

    $jsonStart = strpos($output, '{');
    $jsonEnd = strrpos($output, '}');

    if ($jsonStart === false || $jsonEnd === false) {
        throw new Exception('JSON output not found');
    }

    $json = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);

    $data = json_decode($json, true);
    expect($data)->toBeArray();
    expect($data)->toHaveKey('timing');
    expect($data['timing'])->not->toBeEmpty();
    expect($data['timing'][0])->toHaveKey('file');
    expect($data['timing'][0])->toHaveKey('test');
    expect($data['timing'][0])->toHaveKey('ms');
    expect($data['timing'][0]['file'])->toBe('tests/Fixtures/tests/PassingTest.php');
});

it('filters timing by --over', function () {
    $process = new Process([
        'vendor/bin/pest',
        'tests/Fixtures/tests/PassingTest.php',
        '--over=10000', // very high threshold
        '--colors=never',
        '--quiet',
    ]);

    $process->run();
    $output = trim($process->getOutput());

    expect($output)->not->toContain('CUSTOM_TIMING_START');
});

it('adds summary line to failed files output', function () {
    $process = new Process([
        'vendor/bin/pest',
        'tests/Fixtures/tests/FailingTest.php',
        '--failed-files-only',
        '--colors=never',
        '--quiet',
    ]);

    $process->run();
    $output = trim($process->getOutput());

    expect($output)->toContain('tests/Fixtures/tests/FailingTest.php');
    expect($output)->toContain('1 failing files');
});
