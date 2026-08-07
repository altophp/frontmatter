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

final class BlockScalarTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accepted(): iterable
    {
        yield 'literal clip' => ["v: |\n  a\n  b\n", "a\nb\n"];
        yield 'literal strip' => ["v: |-\n  a\n  b\n\n", "a\nb"];
        yield 'literal keep' => ["v: |+\n  a\n\n", "a\n\n"];
        yield 'folded clip' => ["v: >\n  a\n  b\n", "a b\n"];
        yield 'folded blank line' => ["v: >\n  a\n\n  b\n", "a\nb\n"];
        yield 'folded more-indented' => ["v: >\n  a\n   code\n  b\n", "a\n code\nb\n"];
        yield 'explicit indent' => ["v: |2\n    a\n    b\n", "  a\n  b\n"];
        yield 'chomp before indent' => ["v: |-2\n    a\n", "  a"];
        yield 'empty keep' => ["v: |+\n\n\n", "\n\n"];
        yield 'crlf literal' => ["v: |\r\n  a\r\n  b\r\n", "a\nb\n"];
    }

    #[DataProvider('accepted')]
    public function testAccepted(string $yaml, string $expected): void
    {
        self::assertSame(['v' => $expected], FrontMatter::parse($yaml));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected(): iterable
    {
        yield 'duplicate chomping' => ["v: |--\n  a\n"];
        yield 'duplicate indent' => ["v: |22\n  a\n"];
        yield 'unexpected header tail' => ["v: | x\n  a\n"];
        yield 'bad scalar indentation' => ["v: |3\n  a\n"];
    }

    #[DataProvider('rejected')]
    public function testRejected(string $yaml): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }
}
