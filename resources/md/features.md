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
