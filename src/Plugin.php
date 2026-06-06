<?php

namespace SchenkeIo\TestOutputFormatter;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\Coverage;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\File;

class Plugin implements AddsOutput, HandlesArguments
{
    private bool $failedFilesOnly = false;

    private ?float $under = null;

    /** @var string[] */
    private array $failedFiles = [];

    public function __construct()
    {
        EventFacade::instance()->registerSubscriber(new class($this) implements FailedSubscriber
        {
            public function __construct(private Plugin $plugin) {}

            public function notify(Failed $event): void
            {
                $this->plugin->addFailedFile($event->test());
            }
        });
    }

    public function addFailedFile(Test $test): void
    {
        if ($test instanceof TestMethod) {
            $file = $test->file();
            if (str_contains($file, "eval()'d code")) {
                $parts = explode('(', $file);
                if (count($parts) > 1) {
                    $file = $parts[0];
                }
            }
            // convert absolute path to relative if possible
            $cwd = getcwd();
            if ($cwd && str_starts_with($file, $cwd)) {
                $file = ltrim(substr($file, strlen($cwd)), DIRECTORY_SEPARATOR);
            }
            $this->failedFiles[] = $file;
        }
    }

    /**
     * Inspect and handle CLI arguments passed to Pest.
     */
    public function handleArguments(array $arguments): array
    {
        $newArguments = [];
        $skipNext = false;
        $coverageFound = false;
        $compactFound = false;

        foreach ($arguments as $index => $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if ($arg === '--failed-files-only') {
                $this->failedFilesOnly = true;

                continue;
            }

            if (str_starts_with($arg, '--under=')) {
                $this->under = (float) substr($arg, 8);

                continue;
            }

            if ($arg === '--under') {
                $this->under = isset($arguments[$index + 1]) ? (float) $arguments[$index + 1] : null;
                $skipNext = true;

                continue;
            }

            if ($arg === '--coverage') {
                $coverageFound = true;
            }

            if ($arg === '--compact') {
                $compactFound = true;
            }

            $newArguments[] = $arg;
        }

        if ($this->under !== null) {
            if (! $coverageFound) {
                $newArguments[] = '--coverage';
            }
        }

        return $newArguments;
    }

    /**
     * Intercept and add custom output at the end of the test run.
     */
    public function addOutput(int $exitCode): int
    {
        if ($this->failedFilesOnly && $exitCode !== 0) {
            $uniqueFiles = array_unique($this->failedFiles);
            if (count($uniqueFiles) > 0) {
                echo PHP_EOL;
                foreach ($uniqueFiles as $file) {
                    echo $file.PHP_EOL;
                }
            }
        }

        if ($this->under !== null) {
            $coveragePath = Coverage::getPath();
            if (file_exists($coveragePath)) {
                /** @var CodeCoverage $codeCoverage */
                $codeCoverage = require $coveragePath;
                $report = $codeCoverage->getReport();
                $outputLines = [];
                foreach ($report->getIterator() as $file) {
                    if ($file instanceof File) {
                        $percentage = $file->percentageOfExecutedLines()->asFloat();
                        $fileId = $file->id();
                        if ($percentage < $this->under) {
                            $uncoveredLines = array_keys(array_filter($file->lineCoverageData(), fn ($v) => is_array($v) && count($v) === 0));
                            sort($uncoveredLines);
                            $linesString = $this->getLineRanges($uncoveredLines);

                            $dirname = dirname($fileId);
                            $basename = basename($fileId, '.php');
                            $name = $dirname === '.' ? $basename : $dirname.DIRECTORY_SEPARATOR.$basename;
                            $percentageString = round($percentage).'%';
                            $outputLines[] = "$name ($percentageString: $linesString)";
                        }
                    }
                }
                if (count($outputLines) > 0) {
                    echo 'CUSTOM_UNDER_START'.PHP_EOL;
                    echo "These files are below the given coverage level of {$this->under}".PHP_EOL;
                    foreach ($outputLines as $line) {
                        echo $line.PHP_EOL;
                    }
                    echo 'CUSTOM_UNDER_END'.PHP_EOL;

                    return $exitCode === 0 ? 1 : $exitCode;
                }
            }
        }

        return $exitCode;
    }

    private function getLineRanges(array $lines): string
    {
        if (empty($lines)) {
            return '';
        }
        sort($lines);
        $ranges = [];
        $start = $lines[0];
        $end = $start;
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] == $end + 1) {
                $end = $lines[$i];
            } else {
                $ranges[] = ($start == $end) ? $start : "$start-$end";
                $start = $lines[$i];
                $end = $start;
            }
        }
        $ranges[] = ($start == $end) ? $start : "$start-$end";

        return implode(', ', $ranges);
    }
}
