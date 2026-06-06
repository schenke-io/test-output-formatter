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
            'failedFiles' => $result->failedFiles,
            'underCovered' => array_map(fn ($item) => ['file' => $item['file'], 'cov' => $item['cov']], $result->underCovered),
            'timing' => array_map(fn ($item) => [
                'file' => $item['file'],
                'test' => $item['test'],
                'ms' => round($item['ms'], 2),
            ], $result->timing),
        ];
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        return $exitCode;
    }
}
