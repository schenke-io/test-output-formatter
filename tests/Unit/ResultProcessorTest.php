<?php

use SchenkeIo\TestOutputFormatter\Pest\Options;
use SchenkeIo\TestOutputFormatter\Pest\ResultProcessor;

it('processes and filters timing correctly', function () {
    $options = new Options(slowest: 1, over: 50);
    $processor = new ResultProcessor($options);

    $timing = [
        ['file' => 'A.php', 'test' => 'test1', 'ms' => 100],
        ['file' => 'B.php', 'test' => 'test2', 'ms' => 40], // Below 'over'
        ['file' => 'C.php', 'test' => 'test3', 'ms' => 200],
    ];

    $result = $processor->process([], $timing);

    // Should only have 1 (slowest=1) and it should be the 200ms one
    expect($result->timing)->toHaveCount(1);
    expect($result->timing[0]['file'])->toBe('C.php');
});

it('sorts failed files', function () {
    $options = new Options;
    $processor = new ResultProcessor($options);

    $failedFiles = ['B.php', 'A.php', 'B.php'];
    $result = $processor->process($failedFiles, []);

    expect($result->failedFiles)->toBe(['A.php', 'B.php']);
});

it('handles missing coverage file gracefully', function () {
    $options = new Options(under: 80);
    $processor = new ResultProcessor($options, '/non/existent/path');

    $result = $processor->process([], []);
    expect($result->underCovered)->toBeEmpty();
});

it('finds under-covered files', function () {
    $file = new class
    {
        public function percentageOfExecutedLines()
        {
            return new class
            {
                public function asFloat()
                {
                    return 50.0;
                }
            };
        }

        public function id()
        {
            return 'src/MyFile.php';
        }

        public function lineCoverageData()
        {
            return [10 => []];
        }
    };

    $report = Mockery::mock();
    $report->shouldReceive('getIterator')->andReturn(new ArrayIterator([$file]));

    $cc = Mockery::mock();
    $cc->shouldReceive('getReport')->andReturn($report);

    $options = new Options(under: 100);
    $processor = new ResultProcessor($options);

    $result = $processor->extractUnderCovered($cc);

    expect($result)->not->toBeEmpty();
    expect($result[0]['file'])->toBe('src/MyFile');
});

it('covers the getUnderCovered require block', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'cov');
    file_put_contents($tempFile, '<?php return new class { public function getReport() { return new class { public function getIterator() { return new ArrayIterator([]); } }; } };');

    $options = new Options(under: 80);
    $processor = new ResultProcessor($options, $tempFile);

    $result = $processor->process([], []);
    expect($result->underCovered)->toBeArray();

    unlink($tempFile);
});
