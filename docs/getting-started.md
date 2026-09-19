# Getting started

Read a complete document, access its metadata, and slice the remaining body
from the original source. After [installation](installation.md), save this
script as `metadata.php` beside `vendor/` and run `php metadata.php`.

```php
<?php

require __DIR__.'/vendor/autoload.php';

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

printf("Title: %s\nDraft: %s\nAuthor: %s\nBody: %s\n",
    $title,
    $draft ? 'yes' : 'no',
    $author['name'],
    $body,
);
```

The script prints:

```text
Title: Hello World
Draft: no
Author: Jane
Body: # Hello World
```

Source offsets count bytes in the original string, including its front matter
fences. Slice that same string rather than an already normalized copy.

`fromString()` takes document contents, not a path. It decodes eagerly and
returns an empty `Metadata` object when no front matter block is present.

Use `fromFile()` when the package should read a file:

```php
$metadata = FrontMatter::fromFile('content/article.md');
```

The body is deliberately not stored in `Metadata`. Keeping the original input
under application control avoids a second copy of large documents and lets the
caller decide whether the body should be sliced, streamed, or ignored.

Read [Metadata](metadata.md) for every accessor and [Decoding](decoding.md)
for the accepted syntax.
