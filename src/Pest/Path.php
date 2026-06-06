<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

/**
 * Utility class for path normalization and line range formatting.
 */
class Path
{
    public static function normalize(string $file): string
    {
        if (str_contains($file, "eval()'d code")) {
            $parts = explode('(', $file);
            if (count($parts) > 1) {
                $file = $parts[0];
            }
        }
        $cwd = getcwd();
        if ($cwd && str_starts_with($file, $cwd)) {
            $file = ltrim(substr($file, strlen($cwd)), DIRECTORY_SEPARATOR);
        }

        return $file;
    }

    /**
     * @param  int[]  $lines
     */
    public static function getLineRanges(array $lines): string
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
