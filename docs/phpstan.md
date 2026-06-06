# PHPStan Error Formatter

This package provides a custom PHPStan error formatter that outputs **only the file paths** of files containing errors, one per line. This is useful for piping to other tools or for rapid triage in CI environments.

## Registration

To use these formatters, they are automatically registered via the extension installer. Alternatively, you can register them in your `phpstan.neon` or `phpstan.neon.dist` file:

```neon
services:
    errorFormatter.testOutput:
        class: SchenkeIo\TestOutputFormatter\PHPStan\ErrorFormatter
    errorFormatter.testOutputCompact:
        class: SchenkeIo\TestOutputFormatter\PHPStan\CompactErrorFormatter
    errorFormatter.testOutputJson:
        class: SchenkeIo\TestOutputFormatter\PHPStan\JsonErrorFormatter
```

## Usage

Once registered, you can use the formatters by passing the `--error-format` option to PHPStan:

```bash
# File paths only
vendor/bin/phpstan analyse --error-format=testOutput

# Compact format (file:line message)
vendor/bin/phpstan analyse --error-format=testOutputCompact

# JSON format
vendor/bin/phpstan analyse --error-format=testOutputJson
```

## Output Example

If PHPStan finds errors, the output will look like this:

```text
src/MyClass.php
tests/MyTest.php
```

If no errors are found, the output will be empty and the exit code will be 0.
