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

final class QuotedScalarTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accepted(): iterable
    {
        yield 'single quote doubles quote' => ["v: 'it''s'\n", "it's"];
        yield 'single quote keeps backslash' => ["v: 'a\\nb'\n", 'a\\nb'];
        yield 'double basic escapes' => ["v: \"a\\nb\\t\\\\\\\"\\/\"\n", "a\nb\t\\\"/"];
        yield 'double control escapes' => ["v: \"\\0\\b\\f\\r\"\n", "\0\x08\x0C\r"];
        yield 'ascii unicode escape' => ["v: \"\\u0041\"\n", 'A'];
        yield 'unicode escape' => ["v: \"caf\\u00E9\"\n", "caf\xC3\xA9"];
        yield 'three byte unicode escape' => ["v: \"\\u20AC\"\n", "\xE2\x82\xAC"];
        yield 'surrogate pair' => ["v: \"\\uD83D\\uDE00\"\n", "\xF0\x9F\x98\x80"];
        yield 'quoted value with comment tail' => ["v: \"x\" # note\n", 'x'];
        yield 'quoted flow value' => ["v: ['a: b', \"c # d\"]\n", 'a: b'];
    }

    #[DataProvider('accepted')]
    public function testAccepted(string $yaml, string $expected): void
    {
        $data = FrontMatter::parse($yaml);
        $value = $data['v'];
        if (\is_array($value)) {
            $value = $value[0];
        }

        self::assertSame($expected, $value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected(): iterable
    {
        yield 'unterminated single quote' => ["v: 'x\n"];
        yield 'unterminated double quote' => ["v: \"x\n"];
        yield 'dangling escape' => ['v: "x\\'];
        yield 'unsupported escape' => ["v: \"\\a\"\n"];
        yield 'short unicode escape' => ["v: \"\\u123\"\n"];
        yield 'bad unicode escape' => ["v: \"\\u12XZ\"\n"];
        yield 'unpaired high surrogate' => ["v: \"\\uD83D\"\n"];
        yield 'unpaired low surrogate' => ["v: \"\\uDE00\"\n"];
    }

    #[DataProvider('rejected')]
    public function testRejected(string $yaml): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }
}
