<?php

namespace SchenkeIo\TestOutputFormatter\PHPStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter as PHPStanErrorFormatter;
use PHPStan\Command\Output;

class JsonErrorFormatter implements PHPStanErrorFormatter
{
    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $errors = $analysisResult->getFileSpecificErrors();
        $cwd = getcwd().DIRECTORY_SEPARATOR;

        $jsonErrors = [];
        foreach ($errors as $error) {
            $file = $error->getFile();
            $relativeFile = str_starts_with($file, $cwd) ? substr($file, strlen($cwd)) : $file;

            $jsonError = [
                'file' => $relativeFile,
                'msg' => $error->getMessage(),
            ];

            if ($error->getLine() !== null) {
                $jsonError['line'] = $error->getLine();
            }

            $jsonErrors[] = $jsonError;
        }

        $output->writeRaw((string) json_encode(['errors' => $jsonErrors], JSON_UNESCAPED_SLASHES));

        return $analysisResult->hasErrors() ? 1 : 0;
    }
}
