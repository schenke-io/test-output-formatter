<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

/**
 * Renders the Pest test results as a JSON object.
 */
class JsonRenderer
{
    public function render(Result $result, int $exitCode): int
    {
        $data = [
            'exitCode' => $exitCode,
            'failedFiles' => $result->failedFiles,
            'underCovered' => $result->underCovered,
            'timing' => array_map(fn ($item) => [
                'file' => $item['file'],
                'test' => $item['test'],
                'ms' => round($item['ms'], 2),
            ], $result->timing),
            'coverageMap' => $result->coverageMap,
        ];
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        return $exitCode;
    }
}
