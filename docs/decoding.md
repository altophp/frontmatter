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

## Correct invalid metadata

Use the reported line and column to find the first rejected construct. Replace
unsupported YAML features with explicit values rather than retrying in a
lenient mode: there is none. Quote identifiers when scalar typing differs from
the required type. For TOML input, provide a custom decoder or convert the
metadata to the documented YAML subset before calling the default decoder.

## Guarantees

- A single-pass byte cursor parses without building a token stream or AST.
- Unsupported YAML is rejected rather than silently reinterpreted.
- Scalar typing is deterministic and independent of locale or configuration.
- The first grammar violation reports a line and column.
- Source offsets refer to the original input bytes.
- The package has no runtime Composer dependencies.

Rejecting aliases also prevents alias-expansion attacks by construction. The
decoder never resolves anchors, aliases, or merge keys.

## Limits

- TOML front matter is detected but not decoded.
- The decoder intentionally implements a subset of YAML.
- Metadata access is limited to top-level keys and explicit nested arrays.
- The body remains owned by the caller and is not stored in `Metadata`.
- Parsing stops at the first error and has no lenient mode.

Use [Integration](integration.md) when an application needs replaceable decoder
or renderer contracts.
