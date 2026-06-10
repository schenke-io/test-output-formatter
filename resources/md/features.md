## Features

### PHPStan

- **Error Formatter**: Output only file paths with errors for quick consumption by other tools.
- **Usage**:
  - **File paths only**:
    ```bash
    vendor/bin/phpstan analyse --error-format=testOutput
    ```
  - **Compact format**: (file:line message)
    ```bash
    vendor/bin/phpstan analyse --error-format=testOutputCompact
    ```
  - **JSON format**:
    ```bash
    vendor/bin/phpstan analyse --error-format=testOutputJson
    ```

### Pest

- **Integration**: Plugin to assist in isolating failing tests and checking coverage.
- **Usage**:
  - **Failures only**: Just gives you the list of failing test files.
    ```bash
    vendor/bin/pest --parallel --failed-files-only
    ```
  - **Coverage check**: Reports classes with line coverage below the specified threshold.
    ```bash
    vendor/bin/pest --parallel --under=80
    ```
  - **Slow tests**: Identifies tests that take longer than a threshold or the N slowest tests.
    ```bash
    vendor/bin/pest --parallel --over=100 --slowest=5
    ```
  - **JSON Output**: Returns results as a JSON object (includes exit code, failures, timing, and coverage).
    ```bash
    vendor/bin/pest --format=json
    ```
  - **Caching**: Stores results (failures, timing, coverage map) to speed up subsequent runs.
    ```bash
    vendor/bin/pest --cache-dir=.pest-cache --rerun-failures
    ```
  - **Git-based Selection**: Runs only tests affected by changes since a specific ref or in the current working tree. Requires `--cache-dir` for best results (to use the coverage map).
    ```bash
    vendor/bin/pest --changed
    vendor/bin/pest --since=main
    ```
