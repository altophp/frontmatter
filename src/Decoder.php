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
 * Default decoder for the strict YAML 1.2 core subset.
 *
 * Stateless, so a single instance is safe to share or inject.
 *
 * @phpstan-import-type FrontMatterValue from DecoderInterface
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class Decoder implements DecoderInterface
{
    /**
     * Decode a raw front matter body (no fences) into a PHP array.
     *
     * @return array<array-key, FrontMatterValue>
     *
     * @throws Exception\SyntaxError
     */
    public function decode(string $yaml): array
    {
        return Parser::parse($yaml);
    }
}
