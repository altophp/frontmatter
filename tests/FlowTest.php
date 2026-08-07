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

final class FlowTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function accepted(): iterable
    {
        yield 'empty sequence' => ['v: []', ['v' => []]];
        yield 'empty mapping' => ['v: {}', ['v' => []]];
        yield 'nested' => ['v: [1, {a: [true, null]}, "x"]', ['v' => [1, ['a' => [true, null]], 'x']]];
        yield 'multi-line sequence' => ["v: [\n  a,\n  b\n]\n", ['v' => ['a', 'b']]];
        yield 'multi-line mapping' => ["v: {\n  a: 1,\n  b: two\n}\n", ['v' => ['a' => 1, 'b' => 'two']]];
        yield 'key without value' => ['v: {a, b: 2}', ['v' => ['a' => null, 'b' => 2]]];
        yield 'quoted keys' => ["v: {\"a b\": 1, 'c d': 2}", ['v' => ['a b' => 1, 'c d' => 2]]];
        yield 'comment inside flow' => ["v: [a, # note\n b]\n", ['v' => ['a', 'b']]];
        yield 'crlf flow tail' => ["v: [a]\r\nnext: 1\r\n", ['v' => ['a'], 'next' => 1]];
        yield 'quoted crlf flow value' => ["v: [\"x\"]\r\n", ['v' => ['x']]];
        yield 'spaced flow mapping colon' => ['v: {a : 1}', ['v' => ['a' => 1]]];
        yield 'flow mapping colon after newline' => ["v: {a\n: 1}\n", ['v' => ['a' => 1]]];
        yield 'single null shorthand' => ['v: {a}', ['v' => ['a' => null]]];
        yield 'comment break in flow plain' => ["v: [a # note\n]\n", ['v' => ['a']]];
        yield 'hash without leading space stays plain' => ['v: [a#b]', ['v' => ['a#b']]];
        yield 'colon without delimiter stays plain' => ['v: [http://example.test]', ['v' => ['http://example.test']]];
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
        yield 'trailing sequence comma' => ['v: [a,]'];
        yield 'trailing mapping comma' => ['v: {a: 1,}'];
        yield 'single pair in sequence' => ['v: [a: 1]'];
        yield 'unterminated sequence' => ['v: [a, b'];
        yield 'unterminated mapping' => ['v: {a: 1'];
        yield 'duplicate key' => ['v: {a: 1, a: 2}'];
        yield 'unexpected nested opener in plain' => ['v: [a[b]'];
        yield 'flow tail garbage' => ['v: [a] x'];
        yield 'unsupported key' => ['v: {? a: 1}'];
        yield 'flow value anchor' => ['v: [&x]'];
        yield 'flow value alias' => ['v: [*x]'];
        yield 'flow value tag' => ['v: [!x]'];
        yield 'flow value directive' => ['v: [%x]'];
        yield 'flow value reserved at' => ['v: [@x]'];
        yield 'flow value complex key marker' => ['v: [? x]'];
        yield 'flow value dash marker' => ['v: [- x]'];
        yield 'flow value colon marker' => ['v: [: x]'];
        yield 'empty flow sequence value' => ['v: [,]'];
        yield 'unterminated value after comma' => ['v: [a, '];
        yield 'missing flow sequence separator' => ['v: ["a" "b"]'];
        yield 'unterminated empty mapping' => ['v: {'];
        yield 'quoted flow key missing colon' => ['v: {"a"}'];
        yield 'empty flow mapping key' => ['v: {: 1}'];
        yield 'duplicate null shorthand key' => ['v: {a, a}'];
        yield 'trailing null shorthand comma' => ['v: {a,}'];
        yield 'flow mapping key missing colon' => ["v: {a\n b}\n"];
        yield 'missing flow mapping separator' => ['v: {a: "x" "y"}'];
    }

    #[DataProvider('rejected')]
    public function testRejected(string $yaml): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }

    public function testTooDeepFlowIsRejected(): void
    {
        $yaml = 'v: ' . str_repeat('[', 130) . 'x' . str_repeat(']', 130);

        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }
}
