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

use Alto\FrontMatter\Exception\UnsupportedSyntaxError;
use Alto\FrontMatter\FrontMatter;
use PHPUnit\Framework\TestCase;

final class ExtractionTest extends TestCase
{
    public function testBasicBlockOffsetsAndData(): void
    {
        $doc = "---\ntitle: Hi\n---\nBody text\n";
        $meta = FrontMatter::fromString($doc);
        self::assertSame(['title' => 'Hi'], $meta->all());
        self::assertSame(0, $meta->sourceOffset());
        self::assertSame(18, $meta->sourceLength());
        self::assertSame("Body text\n", substr($doc, $meta->sourceOffset() + $meta->sourceLength()));
    }

    public function testNoFenceAtOffsetZeroIsEmpty(): void
    {
        $meta = FrontMatter::fromString("intro\n---\na: 1\n---\n");
        self::assertSame([], $meta->all());
        self::assertSame(0, $meta->sourceLength());
    }

    public function testIndentedFenceIsNotFrontMatter(): void
    {
        self::assertSame(0, FrontMatter::fromString(" ---\na: 1\n---\n")->sourceLength());
    }

    public function testUnterminatedFenceIsNotFrontMatter(): void
    {
        self::assertSame([], FrontMatter::fromString("---\ntitle: unterminated\n")->all());
        self::assertSame([], FrontMatter::fromString("---\ntitle: unterminated")->all());
    }

    public function testEmptyBlock(): void
    {
        $doc = "---\n---\nBody\n";
        $meta = FrontMatter::fromString($doc);
        self::assertSame([], $meta->all());
        self::assertSame(8, $meta->sourceLength());
        self::assertSame("Body\n", substr($doc, $meta->sourceLength()));
    }

    public function testDotsCloser(): void
    {
        $doc = "---\na: 1\n...\nBody\n";
        $meta = FrontMatter::fromString($doc);
        self::assertSame(['a' => 1], $meta->all());
        self::assertSame(13, $meta->sourceLength());
        self::assertSame("Body\n", substr($doc, $meta->sourceLength()));
    }

    public function testTrailingWhitespaceOnFenceLines(): void
    {
        self::assertSame(['a' => 1], FrontMatter::fromString("---  \na: 1\n---\t\nBody\n")->all());
    }

    public function testTrailingSpacesOnRawLineAreIgnored(): void
    {
        self::assertSame(['a' => 1], FrontMatter::fromString("---\na: 1   \n---\n")->all());
    }

    public function testCrlfDocument(): void
    {
        $doc = "---\r\na: 1\r\nb: two\r\n---\r\nBody\r\n";
        $meta = FrontMatter::fromString($doc);
        self::assertSame(['a' => 1, 'b' => 'two'], $meta->all());
        self::assertSame(0, $meta->sourceOffset());
        self::assertSame("Body\r\n", substr($doc, $meta->sourceOffset() + $meta->sourceLength()));
    }

    public function testUtf8BomShiftsOffset(): void
    {
        $doc = "\xEF\xBB\xBF---\na: 1\n---\nBody\n";
        $meta = FrontMatter::fromString($doc);
        self::assertSame(['a' => 1], $meta->all());
        self::assertSame(3, $meta->sourceOffset());
        self::assertSame(13, $meta->sourceLength());
        self::assertSame("Body\n", substr($doc, $meta->sourceOffset() + $meta->sourceLength()));
    }

    public function testTomlBlockThrowsOnDecode(): void
    {
        $this->expectException(UnsupportedSyntaxError::class);
        FrontMatter::fromString("+++\ntitle = \"x\"\n+++\nBody\n");
    }

    public function testTomlDoesNotCloseOnYamlFences(): void
    {
        self::assertSame([], FrontMatter::fromString("+++\na = 1\n---\n")->all());
    }

    public function testFourDashesIsNotAFence(): void
    {
        self::assertSame([], FrontMatter::fromString("----\na: 1\n---\n")->all());
        self::assertSame([], FrontMatter::fromString("---\na: 1\n----\n")->all());
    }

    public function testDegenerateInputs(): void
    {
        foreach (['', '---', "---\n", '--', "\xEF\xBB\xBF"] as $doc) {
            self::assertSame([], FrontMatter::fromString($doc)->all());
        }
    }

    public function testCloserAtEndOfInputWithoutNewline(): void
    {
        $doc = "---\na: 1\n---";
        $meta = FrontMatter::fromString($doc);
        self::assertSame(['a' => 1], $meta->all());
        self::assertSame(12, $meta->sourceLength());
        self::assertSame('', substr($doc, $meta->sourceOffset() + $meta->sourceLength()));
    }

    public function testCostIgnoresBodySize(): void
    {
        $meta = FrontMatter::fromString('x' . str_repeat("filler line\n", 100000));
        self::assertSame([], $meta->all());
        self::assertSame(0, $meta->sourceLength());
    }
}
