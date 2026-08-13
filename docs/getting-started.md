# Getting Started

Read a complete document, access its metadata, and slice the remaining body
from the original source.

```php
use Alto\FrontMatter\FrontMatter;

$document = <<<'MARKDOWN'
---
title: Hello World
draft: false
weight: 3
tags: [php, alto]
author:
  name: Jane
---
# Hello World
MARKDOWN;

$metadata = FrontMatter::fromString($document);

$title = $metadata->getString('title');
$draft = $metadata->getBoolean('draft', false);
$author = $metadata->all('author');

$body = substr(
    $document,
    $metadata->sourceOffset() + $metadata->sourceLength(),
);
```

`fromString()` takes document contents, not a path. It decodes eagerly and
returns an empty `Metadata` object when no front matter block is present.

Use `fromFile()` when the package should read a file:

```php
$metadata = FrontMatter::fromFile('content/article.md');
```

The body is deliberately not stored in `Metadata`. Keeping the original input
under application control avoids a second copy of large documents and lets the
caller decide whether the body should be sliced, streamed, or ignored.

Read [Typed Metadata](metadata.md) for every accessor and [Decoding](decoding.md)
for the accepted syntax.
