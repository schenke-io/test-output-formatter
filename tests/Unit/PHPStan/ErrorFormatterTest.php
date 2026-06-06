<?php

use PHPStan\Analyser\Error;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\Output;
use SchenkeIo\TestOutputFormatter\PHPStan\ErrorFormatter;

it('outputs only unique file paths when errors exist', function () {
    $formatter = new ErrorFormatter;

    $output = Mockery::mock(Output::class);
    $output->shouldReceive('writeLineFormatted')->once()->with('/path/to/file1.php');
    $output->shouldReceive('writeLineFormatted')->once()->with('/path/to/file2.php');

    $error1 = new Error('message', '/path/to/file1.php', 1);
    $error2 = new Error('message', '/path/to/file1.php', 2); // Same file
    $error3 = new Error('message', '/path/to/file2.php', 3);

    $analysisResult = new AnalysisResult(
        [$error1, $error2, $error3],
        [], // notFileSpecificErrors
        [], // internalErrors
        [], // warnings
        [], // collectedData
        false, // defaultLevelUsed
        null, // projectConfigFile
        false, // savedResultCache
        100, // peakMemoryUsageBytes
        false, // isResultCacheUsed
        [] // changedProjectExtensionFilesOutsideOfAnalysedPaths
    );

    $exitCode = $formatter->formatErrors($analysisResult, $output);

    expect($exitCode)->toBe(1);
});

it('outputs nothing and returns 0 when no errors exist', function () {
    $formatter = new ErrorFormatter;

    $output = Mockery::mock(Output::class);
    $output->shouldNotReceive('writeLineFormatted');

    $analysisResult = new AnalysisResult(
        [], // fileSpecificErrors
        [], // notFileSpecificErrors
        [], // internalErrors
        [], // warnings
        [], // collectedData
        false, // defaultLevelUsed
        null, // projectConfigFile
        false, // savedResultCache
        100, // peakMemoryUsageBytes
        false, // isResultCacheUsed
        [] // changedProjectExtensionFilesOutsideOfAnalysedPaths
    );

    $exitCode = $formatter->formatErrors($analysisResult, $output);

    expect($exitCode)->toBe(0);
});
