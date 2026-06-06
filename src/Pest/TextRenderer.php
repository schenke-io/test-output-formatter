<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

/**
 * Renders the Pest test results as formatted text.
 */
class TextRenderer
{
    public function __construct(private readonly Options $options) {}

    public function render(Result $result, int $exitCode): int
    {
        if ($this->options->failedFilesOnly && count($result->failedFiles) > 0 && $exitCode !== 0) {
            echo PHP_EOL;
            foreach ($result->failedFiles as $file) {
                echo $file.PHP_EOL;
            }
            echo count($result->failedFiles).' failing files'.PHP_EOL;
        }

        if (count($result->underCovered) > 0) {
            echo 'CUSTOM_UNDER_START'.PHP_EOL;
            echo "These files are below the given coverage level of {$this->options->under}".PHP_EOL;
            foreach ($result->underCovered as $item) {
                $percentageString = round($item['cov']).'%';
                echo "{$item['file']} ($percentageString: {$item['lines']})".PHP_EOL;
            }
            echo 'CUSTOM_UNDER_END'.PHP_EOL;

            if ($exitCode === 0) {
                $exitCode = 1;
            }
        }

        if (($this->options->slowest !== null || $this->options->over !== null) && count($result->timing) > 0) {
            echo 'CUSTOM_TIMING_START'.PHP_EOL;
            foreach ($result->timing as $item) {
                $ms = round($item['ms'], 2);
                echo "{$item['file']}: {$item['test']} took {$ms}ms".PHP_EOL;
            }
            echo 'CUSTOM_TIMING_END'.PHP_EOL;
        }

        return $exitCode;
    }
}
