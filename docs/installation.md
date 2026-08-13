# Installation

Install ALTO Front Matter with Composer and verify that the project uses a
supported PHP runtime.

```bash
composer require alto/frontmatter
```

The package requires PHP 8.4 or later. It has no runtime Composer dependencies
and does not require additional PHP extensions. Composer checks the PHP version
when it resolves the package.

After installation, load Composer's autoloader as usual:

```php
require dirname(__DIR__).'/vendor/autoload.php';
```

Continue with [Getting Started](getting-started.md) to read a complete document.
