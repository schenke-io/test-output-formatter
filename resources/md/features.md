## Features

### PHPStan

- **Error Formatter**: Output only file paths with errors for quick consumption by other tools.
- **Usage**:
  ```bash
  vendor/bin/phpstan analyse --error-format=testOutput
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
