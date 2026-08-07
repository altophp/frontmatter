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

final class SequenceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function accepted(): iterable
    {
        yield 'sequence at key indent' => ["tags:\n- a\n- b\nnext: 1\n", ['tags' => ['a', 'b'], 'next' => 1]];
        yield 'sequence indented' => ["tags:\n  - a\n  - b\n", ['tags' => ['a', 'b']]];
        yield 'root sequence' => ["- a\n- 2\n- true\n", ['a', 2, true]];
        yield 'sequence of mappings' => [
            "authors:\n  - name: Ann\n    role: dev\n  - name: Bo\n",
            ['authors' => [['name' => 'Ann', 'role' => 'dev'], ['name' => 'Bo']]],
        ];
        yield 'dash alone with nested mapping' => ["m:\n  -\n    a: 1\n  - x\n", ['m' => [['a' => 1], 'x']]];
        yield 'dash alone is null item' => ["m:\n  -\n  - x\n", ['m' => [null, 'x']]];
        yield 'dash with comment is null item' => ["m:\n  - # nothing\n  - x\n", ['m' => [null, 'x']]];
        yield 'typed items' => ["v:\n  - 1\n  - 2.5\n  - null\n  - yes\n", ['v' => [1, 2.5, null, 'yes']]];
        yield 'quoted items' => ["v:\n  - 'a: b'\n  - \"c # d\"\n", ['v' => ['a: b', 'c # d']]];
        yield 'flow items' => ["v:\n  - [1, 2]\n  - {a: 1}\n", ['v' => [[1, 2], ['a' => 1]]]];
        yield 'quoted key in sequence mapping' => ["v:\n  - \"full name\": Ann\n    age: 3\n", ['v' => [['full name' => 'Ann', 'age' => 3]]]];
        yield 'quoted sequence mapping key with doubled quote' => ["v:\n  - 'it''s': ok\n", ['v' => [["it's" => 'ok']]]];
        yield 'quoted sequence mapping key with escaped quote' => ["v:\n  - \"a\\\"b\": ok\n", ['v' => [['a"b' => 'ok']]]];
        yield 'nested sequence via dash alone' => ["v:\n  -\n    - a\n    - b\n", ['v' => [['a', 'b']]]];
        yield 'item with trailing comment' => ["v:\n  - a # note\n", ['v' => ['a']]];
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
        yield 'sequence entry on key line' => ["a: - b\n"];
        yield 'nested sequence on same line' => ["a:\n  - - b\n"];
        yield 'bad indentation after item' => ["a:\n  - x\n      y: 1\n"];
        yield 'unterminated quoted item is not mapping' => ["a:\n  - 'x\n"];
        yield 'anchor on item' => ["a:\n  - &x 1\n"];
        yield 'alias item' => ["a:\n  - *x\n"];
        yield 'tagged item' => ["a:\n  - !!int 1\n"];
    }

    #[DataProvider('rejected')]
    public function testRejected(string $yaml): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }

    public function testTooDeepSequenceIsRejected(): void
    {
        $yaml = '';
        for ($i = 0; $i < 130; ++$i) {
            $yaml .= str_repeat('  ', $i) . "-\n";
        }
        $yaml .= str_repeat('  ', 130) . "- leaf\n";

        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }
}
