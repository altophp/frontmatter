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

namespace Alto\FrontMatter\Tests;

use Alto\FrontMatter\Exception\SyntaxError;
use Alto\FrontMatter\FrontMatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RejectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupported(): iterable
    {
        yield 'directive' => ["%YAML 1.2\n"];
        yield 'document start' => ["---\na: 1\n"];
        yield 'document end' => ["...\n"];
        yield 'anchor key' => ["&a: 1\n"];
        yield 'alias key' => ["*a: 1\n"];
        yield 'tag key' => ["!tag: 1\n"];
        yield 'complex key' => ["? a\n: b\n"];
        yield 'merge key' => ["<<: *base\n"];
        yield 'reserved at key' => ["@: 1\n"];
        yield 'reserved backtick key' => ["`: 1\n"];
        yield 'reserved at value' => ["v: @x\n"];
        yield 'reserved backtick value' => ["v: `x\n"];
        yield 'directive as value' => ["v: %YAML\n"];
        yield 'complex key marker as value' => ["v: ? complex\n"];
        yield 'plain dash value' => ["v: - x\n"];
        yield 'plain colon value' => ["v: : x\n"];
        yield 'stray comma' => ["v: ,\n"];
        yield 'stray close bracket' => ["v: ]\n"];
        yield 'stray close brace' => ["v: }\n"];
        yield 'flow nesting past MAX_DEPTH' => ['v: ' . str_repeat('[', 130) . str_repeat(']', 130) . "\n"];
        yield 'block sequence nesting past MAX_DEPTH' => ["a:\n  " . str_repeat('- ', 130) . "x\n"];
    }

    #[DataProvider('unsupported')]
    public function testUnsupportedSyntaxIsRejected(string $yaml): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function positionedErrors(): iterable
    {
        yield 'bad plain scalar colon' => ["a: 1\nb: Re: hello\n", 2, 6];
        yield 'unsupported value indicator' => ["a:\n  b: !tagged\n", 2, 6];
        yield 'reserved key indicator' => ["a:\n  @: 1\n", 2, 3];
    }

    #[DataProvider('positionedErrors')]
    public function testErrorPositions(string $yaml, int $line, int $column): void
    {
        try {
            FrontMatter::parse($yaml);
            self::fail('expected SyntaxError');
        } catch (SyntaxError $e) {
            self::assertSame($line, $e->line());
            self::assertSame($column, $e->column());
        }
    }
}
