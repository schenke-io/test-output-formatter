<?php

use SchenkeIo\TestOutputFormatter\Pest\Path;

it('normalizes evald code paths', function () {
    $path = "/path/to/file.php(10) : eval()'d code";
    expect(Path::normalize($path))->toBe('/path/to/file.php');
});

it('makes paths relative to cwd', function () {
    $cwd = getcwd();
    $path = $cwd.DIRECTORY_SEPARATOR.'src/Plugin.php';
    expect(Path::normalize($path))->toBe('src/Plugin.php');
});

it('calculates line ranges correctly', function () {
    expect(Path::getLineRanges([1, 2, 3, 5, 7, 8, 9, 11]))->toBe('1-3, 5, 7-9, 11');
    expect(Path::getLineRanges([]))->toBe('');
    expect(Path::getLineRanges([1]))->toBe('1');
    expect(Path::getLineRanges([1, 2]))->toBe('1-2');
});
