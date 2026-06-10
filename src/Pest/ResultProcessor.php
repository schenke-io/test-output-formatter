<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

use Pest\Support\Coverage;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\File;

class ResultProcessor
{
    /** @var array<string, string[]> */
    private array $coverageMap = [];

    public function __construct(
        private readonly Options $options,
        private readonly ?string $coveragePath = null,
        private readonly ?Cache $cache = null
    ) {}

    /**
     * @param  string[]  $failedFiles
     * @param  array<int, array{file: string, test: string, ms: float}>  $timing
     * @param  array<string, string>  $testIdToFile
     */
    public function process(array $failedFiles, array $timing, array $testIdToFile = []): Result
    {
        $uniqueFailedFiles = array_values(array_unique($failedFiles));
        sort($uniqueFailedFiles);

        $underCovered = $this->getUnderCovered($testIdToFile);

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

        return new Result($uniqueFailedFiles, $underCovered, $timing, $this->coverageMap);
    }

    /**
     * @param  array<string, string>  $testIdToFile
     * @return array<int, array{file: string, cov: float, lines: string}>
     */
    private function getUnderCovered(array $testIdToFile = []): array
    {
        if ($this->cache) {
            $cachedUnder = $this->cache->read('under-covered.json');
            $cachedMap = $this->cache->read('source-to-tests.json');
            if (is_array($cachedUnder)) {
                if (is_array($cachedMap)) {
                    $this->coverageMap = $cachedMap;
                }

                return $cachedUnder;
            }
        }
        $underCovered = [];
        $coveragePath = $this->coveragePath ?? Coverage::getPath();
        if (! file_exists($coveragePath)) {
            return $underCovered;
        }

        /** @var CodeCoverage $codeCoverage */
        $codeCoverage = require $coveragePath;

        return $this->extractUnderCovered($codeCoverage, $testIdToFile);
    }

    /**
     * Extracts under-covered files from CodeCoverage data.
     *
     * Note: We use method_exists() and loose type-hinting for $codeCoverage
     * because PHPUnit's coverage classes are final and cannot be easily mocked.
     * This allows us to use anonymous classes in unit tests.
     *
     * @param  CodeCoverage  $codeCoverage
     * @param  array<string, string>  $testIdToFile
     * @return array<int, array{file: string, cov: float, lines: string}>
     */
    public function extractUnderCovered($codeCoverage, array $testIdToFile = []): array
    {
        $underCovered = [];
        $this->coverageMap = [];
        $report = $codeCoverage->getReport();
        foreach ($report->getIterator() as $file) {
            if (is_object($file) && method_exists($file, 'lineCoverageData')) {
                /** @var File $file */
                $fileId = $file->id();
                $normalizedFile = Path::normalize($fileId);

                // Build coverage map
                foreach ($file->lineCoverageData() as $lineData) {
                    if (is_array($lineData)) {
                        foreach ($lineData as $testId) {
                            if (isset($testIdToFile[$testId])) {
                                $testFile = $testIdToFile[$testId];
                                if (! isset($this->coverageMap[$normalizedFile])) {
                                    $this->coverageMap[$normalizedFile] = [];
                                }
                                if (! in_array($testFile, $this->coverageMap[$normalizedFile])) {
                                    $this->coverageMap[$normalizedFile][] = $testFile;
                                }
                            }
                        }
                    }
                }

                if ($this->options->under !== null) {
                    $percentage = $file->percentageOfExecutedLines()->asFloat();
                    if ($percentage < $this->options->under) {
                        $uncoveredLines = array_keys(array_filter($file->lineCoverageData(), fn ($v) => is_array($v) && count($v) === 0));
                        $linesString = Path::getLineRanges($uncoveredLines);

                        $dirname = dirname($normalizedFile);
                        $basename = basename($normalizedFile, '.php');
                        $name = $dirname === '.' ? $basename : $dirname.DIRECTORY_SEPARATOR.$basename;

                        $underCovered[] = [
                            'file' => $name,
                            'cov' => $percentage,
                            'lines' => $linesString,
                        ];
                    }
                }
            }
        }
        usort($underCovered, fn ($a, $b) => $a['file'] <=> $b['file']);
        ksort($this->coverageMap);
        foreach ($this->coverageMap as &$tests) {
            sort($tests);
        }

        return $underCovered;
    }
}
