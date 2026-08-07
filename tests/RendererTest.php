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
use Alto\FrontMatter\Exception\RenderError;
use Alto\FrontMatter\FrontMatter;
use Alto\FrontMatter\Renderer;
use Alto\FrontMatter\RenderFormat;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    private Renderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new Renderer();
    }

    public function testYamlRenderingRoundTripsThroughParser(): void
    {
        $data = [
            'title' => 'Hello: world',
            'published' => true,
            'weight' => 3,
            'rating' => 4.5,
            'tags' => ['php', 'front matter'],
            'author' => [
                'name' => 'Ada',
                'note' => "line one\nline two",
            ],
        ];

        $yaml = $this->renderer->yaml($data);

        self::assertSame($data, FrontMatter::parse($yaml));
    }

    public function testYamlRenderingSupportsRootListsAndEmptyNestedArrays(): void
    {
        $data = [
            ['name' => 'Ada'],
            [],
            ['tags' => []],
        ];

        $yaml = $this->renderer->yaml($data);

        self::assertSame("-\n  \"name\": \"Ada\"\n- []\n-\n  \"tags\": []\n", $yaml);
        self::assertSame($data, FrontMatter::parse($yaml));
    }

    public function testJsonRenderingRoundTripsThroughParser(): void
    {
        $data = ['title' => 'Hello', 'tags' => ['php', 'json'], 'draft' => false];
        $document = FrontMatter::generate($data, "Body\n", RenderFormat::Json);
        $meta = FrontMatter::fromString($document);

        self::assertSame($data, $meta->all());
        self::assertSame("Body\n", substr($document, $meta->sourceOffset() + $meta->sourceLength()));
    }

    public function testTomlRenderingUsesTomlFences(): void
    {
        $document = FrontMatter::generate([
            'title' => 'Hello',
            'draft' => false,
            'tags' => ['php', 'toml'],
            'author' => ['name' => 'Ada'],
        ], "Body\n", RenderFormat::Toml);

        self::assertSame("+++\n\"title\" = \"Hello\"\n\"draft\" = false\n\"tags\" = [\"php\", \"toml\"]\n\n[\"author\"]\n\"name\" = \"Ada\"\n+++\nBody\n", $document);
    }

    public function testTomlRendererRejectsNull(): void
    {
        $this->expectException(RenderError::class);

        $this->renderer->toml(['missing' => null]);
    }

    public function testTomlRendererRejectsRootLists(): void
    {
        $this->expectException(RenderError::class);

        $this->renderer->toml(['item']);
    }

    public function testTomlRendererRejectsNestedArrays(): void
    {
        $this->expectException(RenderError::class);

        $this->renderer->toml(['matrix' => [[1]]]);
    }

    public function testFacadeRendersYamlByDefault(): void
    {
        self::assertSame("\"title\": \"Hello\"\n", FrontMatter::render(['title' => 'Hello']));
    }

    public function testFacadeRendersJson(): void
    {
        self::assertSame("{\n    \"title\": \"Hello\"\n}\n", FrontMatter::render(['title' => 'Hello'], RenderFormat::Json));
    }

    public function testEmptyYamlRenderingProducesEmptyBlock(): void
    {
        self::assertSame("---\n---\nBody\n", $this->renderer->document([], "Body\n"));
    }

    public function testEmptyTomlRenderingProducesEmptyTomlBlock(): void
    {
        self::assertSame("+++\n+++\nBody\n", $this->renderer->document([], "Body\n", RenderFormat::Toml));
    }

    public function testUnsupportedRendererValueThrowsPackageException(): void
    {
        $this->expectException(RenderError::class);
        $this->expectException(FrontMatterExceptionInterface::class);

        $this->renderer->yaml(['bad' => \INF]);
    }

    public function testInvalidUtf8ThrowsRenderError(): void
    {
        $this->expectException(RenderError::class);

        $this->renderer->json(['bad' => "\xB1"]);
    }

    public function testInvalidUtf8InYamlThrowsRenderError(): void
    {
        $this->expectException(RenderError::class);

        $this->renderer->yaml(['bad' => "\xB1"]);
    }

    public function testFloatRenderingPreservesFullPrecision(): void
    {
        $data = ['value' => 1234567890.123456];

        $yaml = $this->renderer->yaml($data);

        self::assertSame($data, FrontMatter::parse($yaml));
    }

    public function testFloatRenderingKeepsDecimalPointOnWholeNumbers(): void
    {
        self::assertSame("\"value\": 3.0\n", $this->renderer->yaml(['value' => 3.0]));
    }
}
