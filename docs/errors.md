# Errors

Every package exception implements
`Alto\FrontMatter\Exception\FrontMatterExceptionInterface`, allowing one catch
for decoding, access, and rendering failures.

```php
use Alto\FrontMatter\Exception\FrontMatterExceptionInterface;
use Alto\FrontMatter\FrontMatter;

try {
    $metadata = FrontMatter::fromString($document);
} catch (FrontMatterExceptionInterface $error) {
    // Report or reject invalid front matter.
}
```

| Exception | Meaning |
| --- | --- |
| `SyntaxError` | The YAML subset grammar was violated |
| `UnexpectedTypeError` | A typed accessor received the wrong value type |
| `UnsupportedSyntaxError` | A detected block uses unsupported syntax, currently TOML input |
| `RenderError` | A value cannot be represented in the requested format |

`SyntaxError::line()` and `column()` are one-based source coordinates inside
the raw front matter block:

```php
use Alto\FrontMatter\Exception\SyntaxError;

try {
    FrontMatter::fromString("---\ntitle: [unterminated\n---\n");
} catch (SyntaxError $error) {
    printf('%d:%d %s', $error->line(), $error->column(), $error->getMessage());
}
```

`fromFile()` throws `RuntimeException` when its path cannot be read. That error
is outside the package exception interface because it is an I/O failure rather
than a front matter failure.
