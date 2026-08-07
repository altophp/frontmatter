# CHANGELOG

## [0.9.0] - 2026-08-07

First public release. The API is complete but not frozen: it may still change
before 1.0.

### Added
- `FrontMatter::fromString()` and `fromFile()` returning a typed `Metadata`.
- `Metadata` typed accessors: `getString`, `getInt`, `getFloat`, `getBoolean`,
  `getEnum`, `getDate`, plus `get` / `all` / `has` / `keys` and byte offsets
  `sourceOffset()` / `sourceLength()`.
- `FrontMatter::parse()` (raw block to array) and `render()` / `generate()`
  (YAML, JSON, TOML).
- `DecoderInterface` / `Decoder` and `RendererInterface` / `Renderer` contracts.
- Exception hierarchy under `FrontMatterExceptionInterface`.

### Notes
- Requires PHP 8.4 or newer. No runtime Composer dependencies.
- Decoding follows the YAML 1.2 core schema over a documented subset. Anchors,
  aliases, merge keys, tags, and directives are rejected rather than resolved.
- TOML (`+++`) blocks are detected and extracted, but not decoded.

[0.9.0]: https://github.com/altophp/frontmatter/releases/tag/v0.9.0
