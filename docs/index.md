# Alto Front Matter

ALTO Front Matter extracts fenced metadata from documents and exposes it
through typed, read-only accessors. Its dependency-free decoder implements a
strict YAML subset and rejects unsupported syntax with precise source
coordinates instead of silently reinterpreting input.

```php
use Alto\FrontMatter\FrontMatter;

$metadata = FrontMatter::fromString("---\ntitle: Hello\n---\nArticle body\n");
echo $metadata->getString('title');
```

The example prints `Hello`.

## Documentation

- [Installation](installation.md): install the package and verify its requirements.
- [Getting started](getting-started.md): read metadata and recover the document body.
- [Metadata](metadata.md): access typed values, defaults, enums, and dates.
- [Decoding](decoding.md): understand the supported YAML subset and its limits.
- [Rendering](rendering.md): generate YAML, JSON, or TOML front matter.
- [Integration](integration.md): use or replace the decoder and renderer contracts.
- [Errors](errors.md): handle syntax, type, format, and file failures.

## Boundaries

The default decoder accepts YAML front matter only. TOML can be rendered but
requires a custom decoder for input. The returned metadata records where the
front matter appeared; the caller retains ownership of the original document
and its body.
