<?php

namespace SchenkeIo\TestOutputFormatter\PHPStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter as PHPStanErrorFormatter;
use PHPStan\Command\Output;

/**
 * Custom PHPStan error formatter that outputs one line per error
 * in a format easily readable by other tools and AI.
 */
class CompactErrorFormatter implements PHPStanErrorFormatter
{
    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        if (! $analysisResult->hasErrors()) {
            return 0;
        }

        $errors = $analysisResult->getFileSpecificErrors();

        // Sort errors: file path (ASC), then line number (ASC)
        usort($errors, function ($a, $b) {
            if ($a->getFile() !== $b->getFile()) {
                return $a->getFile() <=> $b->getFile();
            }

            return $a->getLine() <=> $b->getLine();
        });

        $cwd = getcwd().DIRECTORY_SEPARATOR;
        $processedErrors = [];
        $fileCount = 0;
        $errorCount = 0;
        $lastFile = null;

        foreach ($errors as $error) {
            $file = $error->getFile();
            $relativeFile = str_starts_with($file, $cwd) ? substr($file, strlen($cwd)) : $file;
            $line = $error->getLine();
            $message = $error->getMessage();

            $errorKey = sprintf('%s:%d:%s', $relativeFile, $line, $message);
            if (isset($processedErrors[$errorKey])) {
                continue;
            }
            $processedErrors[$errorKey] = true;

            if ($lastFile !== $file) {
                $fileCount++;
                $lastFile = $file;
            }
            $errorCount++;

            $output->writeLineFormatted(sprintf(
                '%s:%d  %s',
                $relativeFile,
                $line,
                $message
            ));
        }

        $output->writeLineFormatted(sprintf('%d files, %d errors', $fileCount, $errorCount));

        return 1;
    }
}
