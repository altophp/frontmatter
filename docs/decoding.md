# Decoding

ALTO Front Matter decodes a strict subset of YAML chosen for document metadata.
It follows scalar typing from the YAML 1.2 core schema and rejects unsupported
constructs instead of guessing.

## Raw blocks

Use `parse()` when the fences have already been removed:

```php
use Alto\FrontMatter\FrontMatter;

$data = FrontMatter::parse("title: Hello\ntags: [php, alto]\n");
```

## Supported syntax

- Block mappings and sequences.
- Flow collections such as `[a, b]` and `{name: Jane}`.
- Plain, single-quoted, and double-quoted scalars.
- Unicode escapes, including surrogate pairs.
- Literal and folded block scalars.
- Comments.
- Null, boolean, integer, float, and string scalar values.

JSON objects and arrays are valid YAML flow collections and work inside `---`
front matter fences.

## Rejected syntax

The decoder rejects tabs in indentation, anchors, aliases, tags, directives,
merge keys, multiple documents, complex keys, duplicate keys, hexadecimal and
octal integers, infinities, NaN values, and sexagesimal numbers.

TOML blocks fenced with `+++` are detected, but decoding them raises
`UnsupportedSyntaxError`. TOML remains available as a rendering format.

## Scalar differences

Some values differ from Symfony YAML because this decoder follows the YAML 1.2
core schema without Symfony's additional conventions.

| Input | Symfony YAML | ALTO Front Matter |
| --- | --- | --- |
| `2026-07-08` | Unix timestamp | String |
| `tRUe` | Boolean | String |
| `007` | String | Integer `7` |
| `+42` | Float | Integer `42` |
| `1_000` | Integer | String |
| `0x1A`, `0o17`, `.inf` | Parsed value | `SyntaxError` |

Quote zero-padded identifiers and any other value that must remain a string.
See [Errors](errors.md) for syntax diagnostics.
