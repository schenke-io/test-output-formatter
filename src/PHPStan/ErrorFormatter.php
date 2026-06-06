<?php

namespace SchenkeIo\TestOutputFormatter\PHPStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter as PHPStanErrorFormatter;
use PHPStan\Command\Output;

class ErrorFormatter implements PHPStanErrorFormatter
{
    /**
     * Formats the errors and outputs them to the console.
     *
     * @param  AnalysisResult  $analysisResult  Contains all found errors.
     * @param  Output  $output  Handle for writing to the terminal.
     * @return int Exit code (0 for no errors, 1 if errors are found).
     */
    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        if (! $analysisResult->hasErrors()) {
            return 0;
        }

        $filesWithErrors = [];

        foreach ($analysisResult->getFileSpecificErrors() as $error) {
            $filesWithErrors[$error->getFile()] = true;
        }

        foreach (array_keys($filesWithErrors) as $file) {
            $output->writeLineFormatted($file);
        }

        return 1;
    }
}
