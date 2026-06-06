## Installation

```bash
composer require --dev schenke-io/test-output-formatter
```

### Auto-Registration

The package uses standard discovery mechanisms to integrate with your tools:

- **PHPStan**: The extension is automatically registered via the `phpstan-extension` type in `composer.json` and the `extension.neon` file. For this to work seamlessly, it is highly recommended to install the extension installer:

```bash
composer require --dev phpstan/extension-installer
```

- **Pest**: The plugin is automatically registered through the `extra.pest.plugins` configuration in `composer.json`. No additional configuration is required.
