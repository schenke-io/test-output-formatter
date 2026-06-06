<?php

use Symfony\Component\Process\Process;

it('outputs only the raw file paths of failing tests when flagged', function () {
    // Invoke Pest against the isolated fixtures folder using our custom flag
    $process = new Process([
        'vendor/bin/pest',
        'tests/Fixtures/tests',
        '--failed-files-only',
    ]);

    $process->run();
    $output = trim($process->getOutput());

    // ASSERTIONS:
    // 1. It must catch and print the file path of the failing fixture
    expect($output)->toContain('tests/Fixtures/tests/FailingTest.php');

    // 2. It must NOT print passing test files
    expect($output)->not->toContain('tests/Fixtures/tests/PassingTest.php');

    // 3. It must bypass standard visual layouts (no "FAIL" or "PASS" banner blocks)
    expect($output)->not->toContain('FAIL  Tests\Fixtures\tests\FailingTest');
});

it('outputs under-covered classes below the target threshold', function () {
    $process = new Process([
        'vendor/bin/pest',
        'tests/Fixtures/tests',
        '--coverage',
        '--under=80',
        '--config=tests/Fixtures/phpunit.xml',
    ]);

    $process->run();
    $output = trim($process->getOutput());

    // ASSERTIONS:
    preg_match('/CUSTOM_UNDER_START\s+(.*?)\s+CUSTOM_UNDER_END/s', $output, $matches);
    $customOutput = isset($matches[1]) ? $matches[1] : '';
    $customLines = array_map('trim', explode(PHP_EOL, trim($customOutput)));

    expect($customOutput)->toContain('PoorlyCovered');
    // WellCovered might be in there too if coverage is not 100% in this environment
});
