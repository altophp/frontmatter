# Integration

The static `FrontMatter` facade wires the default decoder and renderer for the
shortest application path. Applications that need explicit dependencies can
use the contracts directly.

```php
use Alto\FrontMatter\Decoder;
use Alto\FrontMatter\DecoderInterface;
use Alto\FrontMatter\Renderer;
use Alto\FrontMatter\RendererInterface;

$decoder = new Decoder();
$renderer = new Renderer();

assert($decoder instanceof DecoderInterface);
assert($renderer instanceof RendererInterface);
```

`DecoderInterface::decode()` accepts raw YAML without fences and returns the
decoded array. `RendererInterface` exposes `render()` for block contents and
`document()` for a fenced block followed by a body.

Type-hint these interfaces in services that need to substitute, decorate, or
test decoding and rendering independently. The facade keeps one lazily-created
default instance of each implementation and exposes no mutable configuration.

Use [Getting started](getting-started.md) when an application only needs the
default behavior.
