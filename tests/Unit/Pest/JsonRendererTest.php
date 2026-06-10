<?php

namespace Tests\Unit\Pest;

use SchenkeIo\TestOutputFormatter\Pest\JsonRenderer;
use SchenkeIo\TestOutputFormatter\Pest\Result;

it('renders result as JSON with all fields', function () {
    $result = new Result(
        failedFiles: ['tests/Feature/FailTest.php'],
        underCovered: [
            ['file' => 'src/Foo.php', 'cov' => 50.0, 'lines' => '1-10'],
        ],
        timing: [
            ['file' => 'tests/Feature/SlowTest.php', 'test' => 'it is slow', 'ms' => 123.456],
        ],
        coverageMap: [
            'src/Foo.php' => ['tests/Feature/FooTest.php'],
        ]
    );

    $renderer = new JsonRenderer;

    ob_start();
    $exitCode = $renderer->render($result, 1);
    $output = ob_get_clean();

    expect($exitCode)->toBe(1);

    $data = json_decode($output, true);

    expect($data)->toBeArray()
        ->toHaveKey('exitCode', 1)
        ->toHaveKey('failedFiles', ['tests/Feature/FailTest.php'])
        ->toHaveKey('underCovered', [['file' => 'src/Foo.php', 'cov' => 50.0, 'lines' => '1-10']])
        ->toHaveKey('timing')
        ->toHaveKey('coverageMap', ['src/Foo.php' => ['tests/Feature/FooTest.php']]);

    expect($data['timing'][0])->toBe([
        'file' => 'tests/Feature/SlowTest.php',
        'test' => 'it is slow',
        'ms' => 123.46,
    ]);
});
