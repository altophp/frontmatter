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

use Alto\FrontMatter\Decoder;
use Alto\FrontMatter\DecoderInterface;
use Alto\FrontMatter\FrontMatter;
use Alto\FrontMatter\Renderer;
use Alto\FrontMatter\RendererInterface;
use Alto\FrontMatter\RenderFormat;
use PHPUnit\Framework\TestCase;

final class ContractsTest extends TestCase
{
    public function testDecoderImplementsInterfaceAndDecodes(): void
    {
        $decoder = new Decoder();
        self::assertInstanceOf(DecoderInterface::class, $decoder);
        self::assertSame(['title' => 'Hi', 'n' => 3], $decoder->decode("title: Hi\nn: 3\n"));
    }

    public function testRendererImplementsInterfaceAndRenders(): void
    {
        $renderer = new Renderer();
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertSame("\"n\": 3\n", $renderer->render(['n' => 3]));
        self::assertSame("---\n\"n\": 3\n---\nBody\n", $renderer->document(['n' => 3], "Body\n"));
    }

    public function testFacadeDelegatesToDefaultDecoder(): void
    {
        $yaml = "title: Hello\ntags: [a, b]\n";
        self::assertSame((new Decoder())->decode($yaml), FrontMatter::parse($yaml));
    }

    public function testFacadeDelegatesToDefaultRenderer(): void
    {
        $data = ['n' => 3, 'tags' => ['a', 'b']];
        self::assertSame((new Renderer())->render($data), FrontMatter::render($data));
        self::assertSame((new Renderer())->document($data, "Body\n"), FrontMatter::generate($data, "Body\n"));
    }

    public function testCustomDecoderCanSatisfyTheContract(): void
    {
        $decoder = new class implements DecoderInterface {
            /**
             * @return array<array-key, mixed>
             */
            public function decode(string $yaml): array
            {
                return ['swapped' => true];
            }
        };

        self::assertInstanceOf(DecoderInterface::class, $decoder);
        self::assertSame(['swapped' => true], $decoder->decode('ignored'));
    }

    public function testCustomRendererCanSatisfyTheContract(): void
    {
        $renderer = new class implements RendererInterface {
            /**
             * @param array<array-key, mixed> $data
             */
            public function render(array $data, RenderFormat $format = RenderFormat::Yaml): string
            {
                return 'custom';
            }

            /**
             * @param array<array-key, mixed> $data
             */
            public function document(array $data, string $body = '', RenderFormat $format = RenderFormat::Yaml): string
            {
                return 'custom-doc';
            }
        };

        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertSame('custom', $renderer->render(['x' => 1]));
        self::assertSame('custom-doc', $renderer->document(['x' => 1]));
    }
}
