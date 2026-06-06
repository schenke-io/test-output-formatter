<?php

use SchenkeIo\TestOutputFormatter\Pest\Options;
use SchenkeIo\TestOutputFormatter\Pest\Result;
use SchenkeIo\TestOutputFormatter\Pest\TextRenderer;

it('renders under-covered files', function () {
    $options = new Options(under: 80);
    $renderer = new TextRenderer($options);

    $result = new Result(
        failedFiles: [],
        underCovered: [
            ['file' => 'src/File.php', 'cov' => 50.0, 'lines' => '10-20'],
        ],
        timing: []
    );

    ob_start();
    $exitCode = $renderer->render($result, 0);
    $output = ob_get_clean();

    expect($output)->toContain('CUSTOM_UNDER_START');
    expect($output)->toContain('These files are below the given coverage level of 80');
    expect($output)->toContain('src/File.php (50%: 10-20)');
    expect($output)->toContain('CUSTOM_UNDER_END');
    expect($exitCode)->toBe(1); // Exit code should change to 1 if under covered
});

it('renders timing information', function () {
    $options = new Options(slowest: 5);
    $renderer = new TextRenderer($options);

    $result = new Result(
        failedFiles: [],
        underCovered: [],
        timing: [
            ['file' => 'tests/MyTest.php', 'test' => 'it works', 'ms' => 123.456],
        ]
    );

    ob_start();
    $exitCode = $renderer->render($result, 0);
    $output = ob_get_clean();

    expect($output)->toContain('CUSTOM_TIMING_START');
    expect($output)->toContain('tests/MyTest.php: it works took 123.46ms');
    expect($output)->toContain('CUSTOM_TIMING_END');
    expect($exitCode)->toBe(0);
});

it('renders failed files only when requested', function () {
    $options = new Options(failedFilesOnly: true);
    $renderer = new TextRenderer($options);

    $result = new Result(
        failedFiles: ['tests/FailTest.php'],
        underCovered: [],
        timing: []
    );

    ob_start();
    $exitCode = $renderer->render($result, 1);
    $output = ob_get_clean();

    expect($output)->toContain('tests/FailTest.php');
    expect($output)->toContain('1 failing files');
    expect($exitCode)->toBe(1);
});
