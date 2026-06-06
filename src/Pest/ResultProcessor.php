<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

use Pest\Support\Coverage;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\File;

class ResultProcessor
{
    public function __construct(
        private readonly Options $options,
        private readonly ?string $coveragePath = null
    ) {}

    /**
     * @param  string[]  $failedFiles
     * @param  array<int, array{file: string, test: string, ms: float}>  $timing
     */
    public function process(array $failedFiles, array $timing): Result
    {
        $uniqueFailedFiles = array_values(array_unique($failedFiles));
        sort($uniqueFailedFiles);

        $underCovered = $this->getUnderCovered();

        if ($this->options->over !== null) {
            $timing = array_filter($timing, fn ($t) => $t['ms'] >= $this->options->over);
        }
        if ($this->options->slowest !== null) {
            usort($timing, fn ($a, $b) => $b['ms'] <=> $a['ms']);
            $timing = array_slice($timing, 0, $this->options->slowest);
        }
        usort($timing, function ($a, $b) {
            if ($a['file'] !== $b['file']) {
                return $a['file'] <=> $b['file'];
            }

            return $a['test'] <=> $b['test'];
        });

        return new Result($uniqueFailedFiles, $underCovered, $timing);
    }

    /**
     * @return array<int, array{file: string, cov: float, lines: string}>
     */
    private function getUnderCovered(): array
    {
        $underCovered = [];
        if ($this->options->under === null) {
            return $underCovered;
        }

        $coveragePath = $this->coveragePath ?? Coverage::getPath();
        if (! file_exists($coveragePath)) {
            return $underCovered;
        }

        /** @var CodeCoverage $codeCoverage */
        $codeCoverage = require $coveragePath;

        return $this->extractUnderCovered($codeCoverage);
    }

    /**
     * Extracts under-covered files from CodeCoverage data.
     *
     * Note: We use method_exists() and loose type-hinting for $codeCoverage
     * because PHPUnit's coverage classes are final and cannot be easily mocked.
     * This allows us to use anonymous classes in unit tests.
     *
     * @param  CodeCoverage  $codeCoverage
     * @return array<int, array{file: string, cov: float, lines: string}>
     */
    public function extractUnderCovered($codeCoverage): array
    {
        $underCovered = [];
        $report = $codeCoverage->getReport();
        foreach ($report->getIterator() as $file) {
            if (is_object($file) && method_exists($file, 'lineCoverageData')) {
                /** @var File $file */
                $percentage = $file->percentageOfExecutedLines()->asFloat();
                $fileId = $file->id();
                if ($percentage < $this->options->under) {
                    $uncoveredLines = array_keys(array_filter($file->lineCoverageData(), fn ($v) => is_array($v) && count($v) === 0));
                    $linesString = Path::getLineRanges($uncoveredLines);

                    $dirname = dirname($fileId);
                    $basename = basename($fileId, '.php');
                    $name = $dirname === '.' ? $basename : $dirname.DIRECTORY_SEPARATOR.$basename;

                    $underCovered[] = [
                        'file' => $name,
                        'cov' => $percentage,
                        'lines' => $linesString,
                    ];
                }
            }
        }
        usort($underCovered, fn ($a, $b) => $a['file'] <=> $b['file']);

        return $underCovered;
    }
}
