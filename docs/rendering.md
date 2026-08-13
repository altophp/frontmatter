# Rendering

Render decoded data as YAML, JSON, or TOML, or generate a complete fenced
document with an optional body.

```php
use Alto\FrontMatter\FrontMatter;
use Alto\FrontMatter\RenderFormat;

$data = [
    'title' => 'Hello',
    'tags' => ['php', 'alto'],
];

$yaml = FrontMatter::render($data);
$json = FrontMatter::render($data, RenderFormat::Json);
$toml = FrontMatter::render($data, RenderFormat::Toml);
```

`render()` returns the block contents without fences. Use `generate()` for a
complete document:

```php
$document = FrontMatter::generate(
    data: $data,
    body: "# Hello\n",
);
```

YAML and JSON documents use `---` fences. TOML documents use `+++` fences.
The renderer accepts scalar values and nested arrays supported by the selected
format. Unsupported values raise `RenderError` rather than being discarded.

Rendering TOML is supported even though TOML decoding is not. See
[Decoding](decoding.md) for the decoder boundary.
