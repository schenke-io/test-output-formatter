<?php

use SchenkeIo\TestOutputFormatter\Pest\Options;

it('parses all flags correctly', function () {
    $args = [
        '--failed-files-only',
        '--slowest=5',
        '--over=100.5',
        '--format=json',
        '--under=50.2',
        '--cache-dir=.pest_cache',
        '--rerun-failures',
        '--since=HEAD~1',
        '--changed',
    ];
    [$options, $newArgs] = Options::fromArguments($args);

    expect($options->failedFilesOnly)->toBeTrue();
    expect($options->slowest)->toBe(5);
    expect($options->over)->toBe(100.5);
    expect($options->json)->toBeTrue();
    expect($options->under)->toBe(50.2);
    expect($options->cacheDir)->toBe(getcwd().DIRECTORY_SEPARATOR.'.pest_cache');
    expect($options->rerunFailures)->toBeTrue();
    expect($options->since)->toBe('HEAD~1');
    expect($options->changed)->toBeTrue();
    expect($newArgs)->toContain('--coverage');
    expect($newArgs)->not->toContain('--cache-dir=.pest_cache');
    expect($newArgs)->not->toContain('--rerun-failures');
    expect($newArgs)->not->toContain('--since=HEAD~1');
    expect($newArgs)->not->toContain('--changed');
});

it('handles --under with space', function () {
    $args = ['--under', '60'];
    [$options, $newArgs] = Options::fromArguments($args);

    expect($options->under)->toBe(60.0);
    expect($newArgs)->toContain('--coverage');
});

it('does not add --coverage if already present', function () {
    $args = ['--under=50', '--coverage'];
    [$options, $newArgs] = Options::fromArguments($args);

    $coverageCount = count(array_filter($newArgs, fn ($a) => $a === '--coverage'));
    expect($coverageCount)->toBe(1);
});

it('does not add --coverage for fast-lane flags', function () {
    $args = ['--failed-files-only', '--rerun-failures', '--since=HEAD', '--changed'];
    [$options, $newArgs] = Options::fromArguments($args);

    expect($newArgs)->not->toContain('--coverage');
});
