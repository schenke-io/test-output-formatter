<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

class Result
{
    /**
     * @param  string[]  $failedFiles
     * @param  array<int, array{file: string, cov: float, lines: string}>  $underCovered
     * @param  array<int, array{file: string, test: string, ms: float}>  $timing
     */
    public function __construct(
        public readonly array $failedFiles,
        public readonly array $underCovered,
        public readonly array $timing,
    ) {}
}
