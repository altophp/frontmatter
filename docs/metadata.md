# Typed Metadata

`Metadata` is a read-only accessor over the decoded top-level mapping. Values
are typed during decoding, and getters validate rather than broadly coerce them.

| Method | Result |
| --- | --- |
| `get(string $key, $default = null)` | Raw value or the default |
| `all(?string $key = null)` | All values or the nested array at a key |
| `has(string $key)` | Whether a top-level key exists |
| `keys()` | Top-level keys |
| `getString(string $key, ?string $default = null)` | String, with integers and floats converted |
| `getInt(string $key, ?int $default = null)` | Integer |
| `getFloat(string $key, ?float $default = null)` | Float, with integers widened |
| `getBoolean(string $key, ?bool $default = null)` | Boolean |
| `getEnum(string $key, string $class, ?BackedEnum $default = null)` | Backed enum case |
| `getDate(string $key, ?DateTimeImmutable $default = null)` | Parsed date |
| `count()` and `getIterator()` | Collection access |

A missing key returns its default. A present value with the wrong type throws
`UnexpectedTypeError`.

```php
$title = $metadata->getString('title', 'Untitled');
$weight = $metadata->getInt('weight', 0);
$published = $metadata->getDate('published');
```

`getDate()` parses a string only when requested. Dates remain strings during
decoding, leaving calendar semantics under application control.

`getEnum()` accepts a backed enum class and checks both the backing type and
the value:

```php
enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}

$status = $metadata->getEnum('status', Status::class, Status::Draft);
```

Access is limited to top-level keys. Use `all('author')` to retrieve a nested
mapping; dot notation is intentionally not supported.

The original block is located by `sourceOffset()` and `sourceLength()`. The
offset is `0`, or `3` after a UTF-8 byte-order mark. A zero length means the
source had no front matter.
