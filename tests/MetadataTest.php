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

use Alto\FrontMatter\Exception\FrontMatterExceptionInterface;
use Alto\FrontMatter\Exception\SyntaxError;
use Alto\FrontMatter\Exception\UnexpectedTypeError;
use Alto\FrontMatter\Exception\UnsupportedSyntaxError;
use Alto\FrontMatter\FrontMatter;
use Alto\FrontMatter\Metadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

enum FixtureStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

enum FixtureLevel: int
{
    case Low = 1;
    case High = 2;
}

final class MetadataTest extends TestCase
{
    private function bag(): Metadata
    {
        return new Metadata([
            'title' => 'Hello',
            'weight' => 3,
            'rating' => 4.5,
            'draft' => false,
            'status' => 'published',
            'bad_status' => 'nope',
            'level' => 1,
            'level_as_string' => '1',
            'tags' => ['php', 'yaml'],
            'author' => ['name' => 'Jane', 'age' => 40],
            'published' => '2026-07-08',
            'bad_date' => 'not a date',
            'nothing' => null,
        ]);
    }

    public function testHasKeysCount(): void
    {
        $bag = $this->bag();
        self::assertTrue($bag->has('title'));
        self::assertTrue($bag->has('nothing'));
        self::assertFalse($bag->has('missing'));
        self::assertContains('author', $bag->keys());
        self::assertCount(13, $bag);
    }

    public function testAllReturnsEverythingOrNestedArray(): void
    {
        $bag = $this->bag();
        self::assertSame('Hello', $bag->all()['title']);
        self::assertSame(['php', 'yaml'], $bag->all('tags'));
        self::assertSame(['name' => 'Jane', 'age' => 40], $bag->all('author'));
        self::assertSame([], $bag->all('missing'));
    }

    public function testGetReturnsRawValueOrDefault(): void
    {
        $bag = $this->bag();
        self::assertSame('Hello', $bag->get('title'));
        self::assertSame(['php', 'yaml'], $bag->get('tags'));
        self::assertSame('fallback', $bag->get('missing', 'fallback'));
        self::assertNull($bag->get('missing'));
    }

    public function testTypedLeafGetters(): void
    {
        $bag = $this->bag();
        self::assertSame('Hello', $bag->getString('title'));
        self::assertSame('3', $bag->getString('weight'));
        self::assertSame(3, $bag->getInt('weight'));
        self::assertSame(3.0, $bag->getFloat('weight'));
        self::assertSame(4.5, $bag->getFloat('rating'));
        self::assertFalse($bag->getBoolean('draft'));
    }

    public function testDefaultsForAbsentOrNull(): void
    {
        $bag = $this->bag();
        self::assertNull($bag->getString('missing'));
        self::assertNull($bag->getInt('missing'));
        self::assertNull($bag->getFloat('missing'));
        self::assertNull($bag->getBoolean('missing'));
        self::assertSame(7, $bag->getInt('missing', 7));
        self::assertSame('x', $bag->getString('nothing', 'x'));
        self::assertTrue($bag->getBoolean('missing', true));
    }

    public function testGetEnum(): void
    {
        $bag = $this->bag();
        self::assertSame(FixtureStatus::Published, $bag->getEnum('status', FixtureStatus::class));
        self::assertSame(FixtureStatus::Draft, $bag->getEnum('missing', FixtureStatus::class, FixtureStatus::Draft));
        self::assertNull($bag->getEnum('missing', FixtureStatus::class));
        self::assertSame(FixtureLevel::Low, $bag->getEnum('level', FixtureLevel::class));
    }

    public function testGetDate(): void
    {
        $bag = $this->bag();
        $date = $bag->getDate('published');
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-07-08', $date->format('Y-m-d'));
        self::assertNull($bag->getDate('missing'));
    }

    /**
     * @return iterable<string, array{callable(Metadata): mixed}>
     */
    public static function mismatches(): iterable
    {
        yield 'int on string' => [static fn(Metadata $b): mixed => $b->getInt('title')];
        yield 'boolean on string' => [static fn(Metadata $b): mixed => $b->getBoolean('title')];
        yield 'float on string' => [static fn(Metadata $b): mixed => $b->getFloat('title')];
        yield 'string on array' => [static fn(Metadata $b): mixed => $b->getString('tags')];
        yield 'all on scalar' => [static fn(Metadata $b): array => $b->all('title')];
        yield 'enum invalid' => [static fn(Metadata $b): mixed => $b->getEnum('bad_status', FixtureStatus::class)];
        yield 'enum on array' => [static fn(Metadata $b): mixed => $b->getEnum('tags', FixtureStatus::class)];
        yield 'enum backing type mismatch' => [static fn(Metadata $b): mixed => $b->getEnum('level_as_string', FixtureLevel::class)];
        yield 'date on int' => [static fn(Metadata $b): mixed => $b->getDate('weight')];
        yield 'date unparsable' => [static fn(Metadata $b): mixed => $b->getDate('bad_date')];
    }

    /**
     * @param callable(Metadata): mixed $access
     */
    #[DataProvider('mismatches')]
    public function testTypeMismatchThrows(callable $access): void
    {
        $this->expectException(UnexpectedTypeError::class);
        $access($this->bag());
    }

    public function testIterates(): void
    {
        $items = iterator_to_array($this->bag());
        self::assertSame('Hello', $items['title']);
        self::assertCount(13, $items);
    }

    public function testExceptionImplementsPackageInterface(): void
    {
        self::assertInstanceOf(FrontMatterExceptionInterface::class, new UnexpectedTypeError('x'));
    }

    public function testUnicodeIsPreserved(): void
    {
        $meta = FrontMatter::fromString("---\ntitle: Café ☕ 日本語\nauthor: André\nemoji: \"🚀\"\nescaped: \"caf\\u00e9 \\uD83D\\uDE80\"\n---\nBody\n");
        self::assertSame('Café ☕ 日本語', $meta->getString('title'));
        self::assertSame('André', $meta->getString('author'));
        self::assertSame('🚀', $meta->getString('emoji'));
        self::assertSame('café 🚀', $meta->getString('escaped'));
    }

    public function testFromFileReadsAndDecodes(): void
    {
        $expected = require __DIR__ . '/Fixtures/01-hugo-post.expected.php';
        self::assertSame($expected, FrontMatter::fromFile(__DIR__ . '/Fixtures/01-hugo-post.md')->all());
    }

    public function testFromFileThrowsWhenMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        FrontMatter::fromFile(__DIR__ . '/Fixtures/does-not-exist.md');
    }

    public function testFromStringReturnsTypedData(): void
    {
        $meta = FrontMatter::fromString("---\ncategory: tutorials\nweight: 3\nauthor:\n  name: Jane\n---\nBody\n");
        self::assertSame('tutorials', $meta->getString('category'));
        self::assertSame(3, $meta->getInt('weight'));
        self::assertSame(['name' => 'Jane'], $meta->all('author'));
        self::assertNull($meta->getString('missing'));
    }

    public function testFromStringReportsSourceOffsetAndLength(): void
    {
        $doc = "---\ntitle: Hi\n---\nBody\n";
        $meta = FrontMatter::fromString($doc);
        self::assertSame(0, $meta->sourceOffset());
        self::assertSame(18, $meta->sourceLength());
        self::assertSame("Body\n", substr($doc, $meta->sourceOffset() + $meta->sourceLength()));
    }

    public function testFromStringIsEmptyWithoutFrontMatter(): void
    {
        $meta = FrontMatter::fromString("# Just markdown\n");
        self::assertCount(0, $meta);
        self::assertSame(0, $meta->sourceLength());
        self::assertNull($meta->getString('category'));
    }

    public function testFromStringThrowsOnMalformedFrontMatter(): void
    {
        $this->expectException(SyntaxError::class);
        FrontMatter::fromString("---\ntitle: [unterminated\n---\nBody\n");
    }

    public function testFromStringThrowsOnTomlBlock(): void
    {
        $this->expectException(UnsupportedSyntaxError::class);
        FrontMatter::fromString("+++\ntitle = \"x\"\n+++\nBody\n");
    }
}
