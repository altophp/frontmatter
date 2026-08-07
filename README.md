# alto/frontmatter

Fast front matter extraction and strict YAML-subset decoding for PHP.

[![CI](https://github.com/altophp/frontmatter/actions/workflows/CI.yml/badge.svg)](https://github.com/altophp/frontmatter/actions/workflows/CI.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.4-777bb4.svg)](composer.json)

`alto/frontmatter` reads the front matter block at the top of a document and
decodes a strict subset of YAML into a typed accessor. It has zero runtime
Composer dependencies. It never touches the filesystem unless you ask it to, and
it keeps only the decoded metadata, not the document body.

```php
use Alto\FrontMatter\FrontMatter;

$meta = FrontMatter::fromString($content);   // or ::fromFile($path)

$meta->getString('title');        // 'Hello World'
$meta->getInt('weight', 0);       // 3
$meta->getBoolean('draft');       // false
$meta->getDate('published');      // DateTimeImmutable
$meta->all('tags');               // ['php', 'yaml']
```

## Features

- One-call read path: `fromString()` / `fromFile()` return a typed `Metadata`.
- Typed accessors that assert, never coerce: `getString`, `getInt`, `getFloat`,
  `getBoolean`, `getEnum`, `getDate`, plus `get` / `all` / `has` / `keys`.
- Scalar typing follows the YAML 1.2 core schema (with documented differences
  from `symfony/yaml`).
- Byte offsets (`sourceOffset()`, `sourceLength()`) so a host can locate the
  block or slice the body without the package holding either.
- Rendering back to YAML, JSON, or TOML fenced blocks.
- Decode and render sit behind `DecoderInterface` / `RendererInterface`, so you
  can inject, mock, or swap them; the static `FrontMatter` facade wires the
  defaults.
- No `preg_*`, no AST, no value objects in the data path. Single-pass byte
  cursor.

## Installation

Requires PHP >= 8.4. No runtime Composer dependencies.

```bash
composer require alto/frontmatter
```

## Usage

### Read a document

`fromString()` takes the document content (not a path). `fromFile()` reads the
file for you. Both decode eagerly and return a `Metadata`, empty when the
document has no front matter.

```php
use Alto\FrontMatter\FrontMatter;

$content = <<<'MD'
---
title: Hello World
draft: false
weight: 3
tags: [php, yaml]
author:
  name: Jane
---
# Body
MD;

$meta = FrontMatter::fromString($content);

$meta->getString('title');   // 'Hello World'
$meta->all('author');        // ['name' => 'Jane']
$meta->has('draft');         // true
```

### Typed accessors (`Metadata`)

Values are already typed by the decoder, so the getters **assert** the type and
throw `UnexpectedTypeError` on a mismatch instead of coercing. A missing key
returns the default (`null` unless you pass one).

| Method | Returns |
| --- | --- |
| `get(string $key, $default = null)` | raw value, or default |
| `all(?string $key = null)` | all values, or the array at a key |
| `has(string $key)` | `bool` |
| `keys()` | `list<array-key>` |
| `getString(string $key, ?string $default = null)` | `?string` (ints/floats cast) |
| `getInt(string $key, ?int $default = null)` | `?int` |
| `getFloat(string $key, ?float $default = null)` | `?float` (ints widened) |
| `getBoolean(string $key, ?bool $default = null)` | `?bool` |
| `getEnum(string $key, string $class, ?\BackedEnum $default = null)` | `?BackedEnum` |
| `getDate(string $key, ?\DateTimeImmutable $default = null)` | `?DateTimeImmutable` (ISO 8601) |
| `count()` / `getIterator()` | `Countable`, `IteratorAggregate` |

`getString('category', null)` returns `null` when the key is absent, the string
when present, and throws when present but not a string. `getDate()` keeps the
decoder fast (dates stay strings at parse time) and parses on demand only when
you ask.

### Locate the body

`Metadata` holds the decoded data plus two integers. The body is not kept; slice
it yourself from the original content:

```php
$body = substr($content, $meta->sourceOffset() + $meta->sourceLength());
```

`sourceOffset()` is 0, or 3 after a UTF-8 BOM. `sourceLength()` is 0 when the
document has no front matter.

### Decode a raw block

If you already isolated the raw block (no fences), decode it directly:

```php
FrontMatter::parse("title: Hello\ntags: [a, b]\n");
// ['title' => 'Hello', 'tags' => ['a', 'b']]
```

### Write

```php
use Alto\FrontMatter\RenderFormat;

FrontMatter::render(['title' => 'Hi', 'tags' => ['a', 'b']]);          // block contents
FrontMatter::generate(['title' => 'Hi'], "Body\n", RenderFormat::Json); // full fenced document
```

`RenderFormat` is `Yaml` (default), `Json`, or `Toml`. Unsupported values throw
`RenderError`.

### Errors

Every exception implements `Alto\FrontMatter\Exception\FrontMatterExceptionInterface`,
so one catch covers the package.

```php
use Alto\FrontMatter\Exception\SyntaxError;

try {
    FrontMatter::fromString("---\ntitle: [unterminated\n---\n");
} catch (SyntaxError $e) {
    $e->getMessage(); // 'unterminated flow sequence, expected "]" at line 2, column 1'
    $e->line();       // 2
    $e->column();     // 1
}
```

- `SyntaxError` (`line()`, `column()`): the block violates the grammar.
- `UnexpectedTypeError`: a typed getter found the wrong type.
- `UnsupportedSyntaxError`: a `+++` TOML block (detected, not decoded).
- `RenderError`: a value cannot be rendered in the target format.

## Contracts and dependency injection

`FrontMatter` is a static facade over default instances. Decoding and rendering
are contracts, so consumers can inject, mock, or swap them:

```php
use Alto\FrontMatter\{Decoder, DecoderInterface, Renderer, RendererInterface};

$decoder = new Decoder();     // implements DecoderInterface
$renderer = new Renderer();   // implements RendererInterface
```

Type-hint `DecoderInterface` / `RendererInterface` in your own services and pass
an instance or a test double.

## The YAML subset

The subset is chosen by measuring real front matter (Hugo, Jekyll, Astro, Zola,
Obsidian), not by reading the YAML spec. Decoding follows the YAML 1.2 core
schema. The parser rejects what it does not support; it never silently
reinterprets.

**Supported:** block mappings and sequences, flow collections (`[a, b]`,
`{k: v}`), plain/single/double-quoted scalars (with `\uXXXX` and surrogate
pairs), literal `|` and folded `>` block scalars, comments. Scalar typing: null
(`~`, empty, `null`), bool (`true`/`false` and case variants), int, float,
everything else string. Dates stay strings; the caller decides calendar
semantics.

**Rejected with `SyntaxError`:** tabs in indentation, anchors/aliases/tags/
directives, merge keys, multi-document markers, complex or duplicate keys, hex
`0x`, octal `0o`, `.inf`, `.nan`, sexagesimal numbers.

JSON front matter works inside `---` fences (JSON objects and arrays are valid
YAML flow). TOML (`+++`) is detected and extracted, but not decoded:
`fromString()` throws `UnsupportedSyntaxError` on a TOML block.

### Differences with Symfony YAML

`symfony/yaml` implements a subset of YAML 1.2 but keeps some YAML 1.1
conventions and a few behaviors of its own, so some scalars decode differently.
This parser follows the YAML 1.2 core schema.

| Input | `symfony/yaml` | `alto/frontmatter` |
| --- | --- | --- |
| `2026-07-08` | `1783468800` (Unix timestamp) | `'2026-07-08'` (string) |
| `tRUe` | `true` (any case) | `'tRUe'` (string) |
| `007` | `'007'` (string) | `7` (integer) |
| `+42` | `42.0` (float) | `42` (integer) |
| `1_000` | `1000` (integer) | `'1_000'` (string) |
| `0x1A`, `0o17`, `.inf` | parsed | `SyntaxError` |

Note that `007` decodes to the integer `7` and a very large integer decodes to a
float; quote such values (`"007"`) to keep zero-padded identifiers as strings.

Beyond scalar typing, this parser rejects structural YAML features that
`symfony/yaml` accepts. Anchors (`&`), aliases (`*`), merge keys (`<<`), tags
(`!`), and directives (`%`) all throw `SyntaxError` instead of being resolved.
These do not appear in real front matter, and rejecting aliases is also a
safety choice: the decoder is immune by construction to the "billion laughs"
alias-expansion attack, which matters when the input is untrusted (a Markdown
file authored by a user).

## Design goals

- **Speed first.** Single-pass, allocation-light byte-cursor parsing. When a
  YAML feature conflicts with that, the feature is dropped.
- **A subset, not a dialect.** Follows the YAML 1.2 core schema; rejects the
  rest rather than guessing.
- **Bytes in, typed values out.** No token stream, no AST. Offsets are original
  input bytes.
- **Fail fast.** The first grammar violation throws with a line and column. No
  lenient mode.
- **Deterministic typing.** A given scalar always decodes to the same PHP type.
  No locale, environment, or configuration affects the result.

## Development

```bash
composer qa        # phpstan (max), php-cs-fixer, phpunit
composer tests     # phpunit only
composer coverage  # phpunit with a 100% line-coverage floor
```

The suite runs only this library against committed fixtures: 272 tests, 100%
line coverage, no third-party parser at test time.

## Limitations

- TOML front matter (`+++`) is detected and extracted, but not decoded.
- The decoder is a subset, not a full YAML parser (see the rejected list above).

## License

`alto/frontmatter` is licensed under the MIT license. See the [LICENSE](LICENSE)
file for details.
