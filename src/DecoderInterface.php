<?php

declare(strict_types=1);

/*
 * This file is part of the ALTO library.
 *
 * © 2026-present Simon André
 *
 * For full copyright and license information, please see
 * the LICENSE file distributed with this source code.
 */

namespace Alto\FrontMatter;

/**
 * Decodes a raw front matter body into a PHP array.
 *
 * A decoded value is a YAML 1.2 core scalar or a nested array of them. The
 * alias stops the nesting at `mixed` because PHPStan cannot express the fully
 * recursive type; the typed getters on Metadata give the precise leaf types.
 *
 * @phpstan-type FrontMatterValue int|float|string|bool|null|array<array-key, mixed>
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface DecoderInterface
{
    /**
     * Decode a raw front matter body (no fences) into a PHP array.
     *
     * @return array<array-key, FrontMatterValue>
     *
     * @throws Exception\SyntaxError
     */
    public function decode(string $yaml): array;
}
