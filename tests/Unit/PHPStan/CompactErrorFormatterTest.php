<?php

use PHPStan\Analyser\Error;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\Output;
use SchenkeIo\TestOutputFormatter\PHPStan\CompactErrorFormatter;

it('formats errors compactly with sorting and deduplication', function () {
    $formatter = new CompactErrorFormatter;
    $cwd = getcwd().DIRECTORY_SEPARATOR;

    $output = Mockery::mock(Output::class);
    // Sort order should be file2 then file1 (if file2 < file1, but usually file1 < file2)
    // Let's use fileA.php and fileB.php to be sure
    $fileA = $cwd.'fileA.php';
    $fileB = $cwd.'fileB.php';

    $error1 = new Error('error message 1', $fileB, 20);
    $error2 = new Error('error message 1', $fileB, 10); // fileB:10 comes before fileB:20
    $error3 = new Error('error message 2', $fileA, 5);  // fileA comes before fileB
    $error4 = new Error('error message 2', $fileA, 5);  // duplicate of error3

    $output->shouldReceive('writeLineFormatted')->once()->with('fileA.php:5  error message 2');
    $output->shouldReceive('writeLineFormatted')->once()->with('fileB.php:10  error message 1');
    $output->shouldReceive('writeLineFormatted')->once()->with('fileB.php:20  error message 1');
    $output->shouldReceive('writeLineFormatted')->once()->with('2 files, 3 errors');

    $analysisResult = new AnalysisResult(
        [$error1, $error2, $error3, $error4],
        [], [], [], [], false, null, false, 100, false, []
    );

    $exitCode = $formatter->formatErrors($analysisResult, $output);

    expect($exitCode)->toBe(1);
});

it('returns 0 and outputs nothing when there are no errors', function () {
    $formatter = new CompactErrorFormatter;
    $output = Mockery::mock(Output::class);
    $output->shouldNotReceive('writeLineFormatted');

    $analysisResult = new AnalysisResult(
        [], [], [], [], [], false, null, false, 100, false, []
    );

    $exitCode = $formatter->formatErrors($analysisResult, $output);

    expect($exitCode)->toBe(0);
});
