<?php

namespace SchenkeIo\TestOutputFormatter\Pest;

/**
 * DTO for Pest plugin options.
 */
class Options
{
    public function __construct(
        public readonly bool $failedFilesOnly = false,
        public readonly ?float $under = null,
        public readonly ?int $slowest = null,
        public readonly ?float $over = null,
        public readonly bool $json = false,
    ) {}

    /**
     * @param  string[]  $arguments
     * @return array{0: self, 1: string[]}
     */
    public static function fromArguments(array $arguments): array
    {
        $failedFilesOnly = false;
        $under = null;
        $slowest = null;
        $over = null;
        $json = false;

        $newArguments = [];
        $skipNext = false;
        $coverageFound = false;

        foreach ($arguments as $index => $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if ($arg === '--failed-files-only') {
                $failedFilesOnly = true;

                continue;
            }

            if (str_starts_with($arg, '--slowest=')) {
                $slowest = (int) substr($arg, 10);

                continue;
            }

            if (str_starts_with($arg, '--over=')) {
                $over = (float) substr($arg, 7);

                continue;
            }

            if ($arg === '--format=json') {
                $json = true;

                continue;
            }

            if (str_starts_with($arg, '--under=')) {
                $under = (float) substr($arg, 8);

                continue;
            }

            if ($arg === '--under') {
                $under = isset($arguments[$index + 1]) ? (float) $arguments[$index + 1] : null;
                $skipNext = true;

                continue;
            }

            if ($arg === '--coverage') {
                $coverageFound = true;
            }

            $newArguments[] = $arg;
        }

        if ($under !== null && ! $coverageFound) {
            $newArguments[] = '--coverage';
        }

        return [
            new self($failedFilesOnly, $under, $slowest, $over, $json),
            $newArguments,
        ];
    }
}
