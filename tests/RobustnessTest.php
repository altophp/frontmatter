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

final class RobustnessTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function mutatedInputs(): iterable
    {
        $samples = [
            "a: 1\nb:\n  - x\n  - {y: [true, null]}\n",
            "title: \"hello\"\ndescription: >\n  one\n  two\n",
            "v: [a, b, {c: d}]\n",
        ];

        foreach ($samples as $sampleIndex => $sample) {
            yield "truncate-$sampleIndex" => [substr($sample, 0, max(0, strlen($sample) - 3))];
            yield "flip-$sampleIndex" => [substr_replace($sample, "\xFF", intdiv(strlen($sample), 2), 1)];
            yield "shuffle-lines-$sampleIndex" => [self::swapFirstTwoLines($sample)];
        }
    }

    #[DataProvider('mutatedInputs')]
    public function testMutationsReturnArrayOrSyntaxError(string $yaml): void
    {
        try {
            $decoded = FrontMatter::parse($yaml);
            self::assertSame($decoded, FrontMatter::parse($yaml));
        } catch (SyntaxError $e) {
            self::assertGreaterThanOrEqual(1, $e->line());
            self::assertGreaterThanOrEqual(1, $e->column());
        }
    }

    private static function swapFirstTwoLines(string $sample): string
    {
        $lines = explode("\n", $sample);
        if (\count($lines) < 2) {
            return $sample;
        }
        [$lines[0], $lines[1]] = [$lines[1], $lines[0]];

        return implode("\n", $lines);
    }
}
