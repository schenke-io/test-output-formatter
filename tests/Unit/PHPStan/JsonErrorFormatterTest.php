<?php

use PHPStan\Analyser\Error;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\Output;
use SchenkeIo\TestOutputFormatter\PHPStan\JsonErrorFormatter;

it('formats errors as json', function () {
    $formatter = new JsonErrorFormatter;
    $cwd = getcwd().DIRECTORY_SEPARATOR;

    $output = Mockery::mock(Output::class);

    $fileA = $cwd.'fileA.php';
    $error1 = new Error('error message 1', $fileA, 10);

    $expectedJson = json_encode([
        'errors' => [
            [
                'file' => 'fileA.php',
                'msg' => 'error message 1',
                'line' => 10,
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);

    $output->shouldReceive('writeRaw')->once()->with($expectedJson);

    $analysisResult = new AnalysisResult(
        [$error1],
        [], [], [], [], false, null, false, 100, false, []
    );

    $exitCode = $formatter->formatErrors($analysisResult, $output);

    expect($exitCode)->toBe(1);
});

it('omits line key if line is null in json output', function () {
    $formatter = new JsonErrorFormatter;
    $cwd = getcwd().DIRECTORY_SEPARATOR;

    $output = Mockery::mock(Output::class);

    $fileA = $cwd.'fileA.php';
    $error1 = new Error('error message 1', $fileA, null);

    $expectedJson = json_encode([
        'errors' => [
            [
                'file' => 'fileA.php',
                'msg' => 'error message 1',
                // line is missing
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);

    $output->shouldReceive('writeRaw')->once()->with($expectedJson);

    $analysisResult = new AnalysisResult(
        [$error1],
        [], [], [], [], false, null, false, 100, false, []
    );

    $formatter->formatErrors($analysisResult, $output);
});
