# PHPStan Error Formatter

This package provides a custom PHPStan error formatter that outputs **only the file paths** of files containing errors, one per line. This is useful for piping to other tools or for rapid triage in CI environments.

## Registration

To use this formatter, register it in your `phpstan.neon` or `phpstan.neon.dist` file:

```neon
services:
    errorFormatter.triage:
        class: SchenkeIo\TestOutputFormatter\PHPStan\ErrorFormatter
```

## Usage

Once registered, you can use the formatter by passing the `--error-format` option to PHPStan:

```bash
vendor/bin/phpstan analyse --error-format=triage
```

## Output Example

If PHPStan finds errors, the output will look like this:

```text
src/MyClass.php
tests/MyTest.php
```

If no errors are found, the output will be empty and the exit code will be 0.
