# Test Output Formatter Skill

This package provides a Pest plugin and PHPStan error formatters to isolate failing tests, check coverage, and identify slow tests.

## Pest Plugin Flags

### `--format=json`
Returns results as a JSON object. Useful for CI and AI agents.
- `exitCode`: The exit code of the Pest run.
- `failedFiles`: List of failing test files.
- `underCovered`: List of files below coverage threshold, including uncovered lines.
- `timing`: List of tests and their execution time.
- `coverageMap`: Mapping from source files to the tests that cover them.

### `--cache-dir=<dir>`
Specifies a directory to store test results. Required for `--rerun-failures`, `--changed`, and `--since`.

### `--rerun-failures`
Only runs tests that failed in the previous run (requires `--cache-dir`).

### `--changed`
Runs tests affected by changes in the current Git working tree.
- Uses `coverageMap` from cache to identify which tests to run for changed source files.
- Fails open (runs all tests) if no coverage map is found or if a source file is not in the map.

### `--since=<ref>`
Runs tests affected by changes since the specified Git reference (e.g., `main`).
- Same logic as `--changed`.

### `--under=<percentage>`
Reports files with coverage below the specified percentage. Triggers `--coverage` automatically if not present.

### `--slowest=<count>`
Reports the N slowest tests.

### `--over=<ms>`
Reports tests taking longer than the specified milliseconds.

## PHPStan Error Formatters

- `testOutput`: File paths only.
- `testOutputCompact`: File, line, and message.
- `testOutputJson`: Full error details in JSON.

## AI Agent Integration

When working with an AI agent:
1. Use `--format=json` to get a machine-readable summary of failures.
2. Use `--cache-dir` to maintain state across multiple agent steps.
3. Use `--rerun-failures` to quickly verify fixes.
4. Use `--changed` or `--since` to minimize the test suite for the current task.
