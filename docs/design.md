# Design

ALTO Front Matter favors predictable document metadata over complete YAML
compatibility.

## Guarantees

- A single-pass byte cursor performs parsing without a token stream or AST.
- Unsupported YAML is rejected rather than silently reinterpreted.
- Scalar typing is deterministic and independent of locale or configuration.
- The first grammar violation reports a line and column.
- Source offsets refer to the original input bytes.
- The package has no runtime Composer dependencies.

Rejecting aliases also prevents alias-expansion attacks by construction. The
decoder never resolves anchors, aliases, or merge keys.

## Limitations

- TOML front matter is detected but not decoded.
- The decoder intentionally implements a subset rather than the complete YAML specification.
- Metadata access is limited to top-level keys and explicit nested arrays.
- The body remains owned by the caller and is not stored in `Metadata`.
- Parsing stops at the first error and has no lenient mode.

Read [Decoding](decoding.md) for the exact syntax boundary and
[Integration](integration.md) for the replaceable contracts.
