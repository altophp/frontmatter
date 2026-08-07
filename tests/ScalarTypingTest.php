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

final class ScalarTypingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function accepted(): iterable
    {
        yield 'empty value' => ['v:', null];
        yield 'tilde null' => ['v: ~', null];
        yield 'lower null' => ['v: null', null];
        yield 'upper null' => ['v: NULL', null];
        yield 'true' => ['v: true', true];
        yield 'false' => ['v: false', false];
        yield 'title true' => ['v: True', true];
        yield 'yaml 1.1 yes stays string' => ['v: yes', 'yes'];
        yield 'yaml 1.1 off stays string' => ['v: off', 'off'];
        yield 'positive int' => ['v: 42', 42];
        yield 'signed int' => ['v: -42', -42];
        yield 'plus int' => ['v: +42', 42];
        yield 'int max' => ['v: 9223372036854775807', 9223372036854775807];
        yield 'int min' => ['v: -9223372036854775808', -9223372036854775807 - 1];
        yield 'too large int becomes float' => ['v: 9223372036854775808', 9.223372036854776E+18];
        yield 'decimal float' => ['v: 3.5', 3.5];
        yield 'leading dot float' => ['v: .5', 0.5];
        yield 'exponent float' => ['v: 1.5e-2', 0.015];
        yield 'malformed exponent stays string' => ['v: 1e', '1e'];
        yield 'date stays string' => ['v: 2026-07-06', '2026-07-06'];
        yield 'leading zero decimal stays decimal' => ['v: 012', 12];
        yield 'sign only stays string' => ['v: +', '+'];
        yield 'dot only stays string' => ['v: .', '.'];
        yield 'all zero large int stays zero' => ['v: 0000000000000000000', 0];
    }

    #[DataProvider('accepted')]
    public function testAccepted(string $yaml, mixed $expected): void
    {
        self::assertSame(['v' => $expected], FrontMatter::parse($yaml));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected(): iterable
    {
        yield 'hex' => ['v: 0x10'];
        yield 'octal' => ['v: 0o10'];
        yield 'inf' => ['v: .inf'];
        yield 'nan' => ['v: .NaN'];
    }

    #[DataProvider('rejected')]
    public function testRejected(string $yaml): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::parse($yaml);
    }
}
