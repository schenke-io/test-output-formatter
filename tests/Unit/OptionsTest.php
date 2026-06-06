<?php

use SchenkeIo\TestOutputFormatter\Pest\Options;

it('parses all flags correctly', function () {
    $args = ['--failed-files-only', '--slowest=5', '--over=100.5', '--format=json', '--under=50.2'];
    [$options, $newArgs] = Options::fromArguments($args);

    expect($options->failedFilesOnly)->toBeTrue();
    expect($options->slowest)->toBe(5);
    expect($options->over)->toBe(100.5);
    expect($options->json)->toBeTrue();
    expect($options->under)->toBe(50.2);
    expect($newArgs)->toContain('--coverage');
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
