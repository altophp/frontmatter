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

use Alto\FrontMatter\FrontMatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/Fixtures';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fixtures(): iterable
    {
        $dir = self::FIXTURES_DIR;
        $files = glob($dir . '/*.md');
        if (false === $files) {
            return;
        }
        foreach ($files as $mdFile) {
            $base = basename($mdFile, '.md');
            $expectedFile = $dir . '/' . $base . '.expected.php';
            if (!file_exists($expectedFile)) {
                continue;
            }
            yield $base => [$mdFile, $expectedFile];
        }
    }

    #[DataProvider('fixtures')]
    public function testFixtureMatchesExpected(string $mdFile, string $expectedFile): void
    {
        $input = file_get_contents($mdFile);
        self::assertIsString($input);

        $expected = require $expectedFile;

        self::assertSame($expected, FrontMatter::fromString($input)->all(), sprintf('Mismatch for %s', basename($mdFile)));
    }
}
