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

final class MappingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function accepted(): iterable
    {
        yield 'flat' => ["a: 1\nb: two\n", ['a' => 1, 'b' => 'two']];
        yield 'nested' => ["a:\n  b:\n    c: 1\n  d: 2\ne: 3\n", ['a' => ['b' => ['c' => 1], 'd' => 2], 'e' => 3]];
        yield 'empty value is null' => ["a:\nb: 1\n", ['a' => null, 'b' => 1]];
        yield 'empty value at eof' => ['a:', ['a' => null]];
        yield 'quoted keys' => ["\"a b\": 1\n'c d': 2\n", ['a b' => 1, 'c d' => 2]];
        yield 'key with inner spaces' => ["main title: x\n", ['main title' => 'x']];
        yield 'value with trailing comment' => ["a: 1 # note\n", ['a' => 1]];
        yield 'comment only value' => ["a: # note\n  b: 1\n", ['a' => ['b' => 1]]];
        yield 'blank and comment lines between keys' => ["a: 1\n\n# note\n\nb: 2\n", ['a' => 1, 'b' => 2]];
        yield 'colon in value without space' => ["url: https://example.com/x\ntime: 12:30\n", ['url' => 'https://example.com/x', 'time' => '12:30']];
        yield 'blank input' => ['', []];
        yield 'comment only input' => ["# just a comment\n", []];
        yield 'root indent above zero' => ["  a: 1\n  b: 2\n", ['a' => 1, 'b' => 2]];
        yield 'numeric-looking key stays string' => ["2026: year\n", ['2026' => 'year']];
        yield 'crlf lines' => ["a: 1\r\nb: 2\r\n", ['a' => 1, 'b' => 2]];
    }

    /**
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('accepted')]
    public function testAccepted(string $yaml, array $expected): void
    {
        self::assertSame($expected, FrontMatter::parse($yaml));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected(): iterable
    {
        yield 'duplicate key' => ["a: 1\na: 2\n"];
        yield 'tab in indentation' => ["a:\n\tb: 1\n"];
        yield 'sibling indent mismatch deeper' => ["a: 1\n  b: 2\n"];
        yield 'child indent mismatch' => ["a:\n    b: 1\n  c: 2\n"];
        yield 'missing colon' => ["just a scalar\n"];
        yield 'empty key' => [": 1\n"];
        yield 'sequence entry inside mapping' => ["a: 1\n- b\n"];
        yield 'plain value containing colon space' => ["title: Re: hello\n"];
        yield 'content after quoted value' => ["a: \"x\" y\n"];
        yield 'multi-line plain continuation' => ["a: first\n  second\n"];
        yield 'root trailing content' => ["  a: 1\nb: 2\n"];
        yield 'quoted key missing colon' => ["\"a\" 1\n"];
        yield 'quoted key colon without space' => ["\"a\":1\n"];
        yield 'comment break before colon' => ["a # note\n"];
    }

    #[DataProvider('rejected')]
    public function testRejected(string $yaml): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }

    public function testDuplicateKeyErrorPosition(): void
    {
        try {
            FrontMatter::parse("a: 1\nb: 2\na: 3\n");
            self::fail('expected SyntaxError');
        } catch (SyntaxError $e) {
            self::assertSame(3, $e->line());
            self::assertSame(1, $e->column());
        }
    }

    public function testTabIndentationErrorPosition(): void
    {
        try {
            FrontMatter::parse("a:\n\tb: 1\n");
            self::fail('expected SyntaxError');
        } catch (SyntaxError $e) {
            self::assertSame(2, $e->line());
            self::assertSame(1, $e->column());
        }
    }

    public function testTooDeepMappingIsRejected(): void
    {
        $yaml = '';
        for ($i = 0; $i < 130; ++$i) {
            $yaml .= str_repeat('  ', $i) . 'k' . $i . ":\n";
        }
        $yaml .= str_repeat('  ', 130) . "leaf: 1\n";

        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }
}
