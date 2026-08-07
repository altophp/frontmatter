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

namespace Alto\FrontMatter;

/**
 * Extracts and decodes a document's front matter.
 *
 * @phpstan-import-type FrontMatterValue from DecoderInterface
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class FrontMatter
{
    private const string BOM = "\xEF\xBB\xBF";

    private static ?DecoderInterface $decoder = null;

    private static ?RendererInterface $renderer = null;

    /**
     * Extract and decode the front matter of a document string.
     *
     * Returns the metadata as a typed accessor, empty when the document has no
     * front matter. Decodes eagerly and throws on a malformed block.
     *
     * @throws Exception\SyntaxError
     * @throws Exception\UnsupportedSyntaxError when the block uses "+++" TOML fences
     */
    public static function fromString(string $input): Metadata
    {
        $block = self::locate($input);
        if (null === $block) {
            return new Metadata([]);
        }
        if (Syntax::Toml === $block['syntax']) {
            throw new Exception\UnsupportedSyntaxError('TOML front matter decoding is not supported, only extraction');
        }
        $raw = substr($input, $block['rawStart'], $block['rawLength']);

        return new Metadata(self::decoder()->decode($raw), $block['offset'], $block['length']);
    }

    /**
     * Extract and decode the front matter of a file.
     *
     * @throws \RuntimeException when the file cannot be read
     * @throws Exception\SyntaxError
     * @throws Exception\UnsupportedSyntaxError when the block uses "+++" TOML fences
     */
    public static function fromFile(string $path): Metadata
    {
        $content = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if (false === $content) {
            throw new \RuntimeException(sprintf('Unable to read file "%s".', $path));
        }

        return self::fromString($content);
    }

    /**
     * Decode a raw front matter body (no fences) from the strict YAML subset.
     *
     * @return array<array-key, FrontMatterValue>
     *
     * @throws Exception\SyntaxError
     */
    public static function parse(string $yaml): array
    {
        return self::decoder()->decode($yaml);
    }

    /**
     * Render decoded front matter data as YAML subset, JSON, or TOML.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws Exception\RenderError
     */
    public static function render(array $data, RenderFormat $format = RenderFormat::Yaml): string
    {
        return self::renderer()->render($data, $format);
    }

    /**
     * Generate a fenced front matter block followed by an optional body.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws Exception\RenderError
     */
    public static function generate(array $data, string $body = '', RenderFormat $format = RenderFormat::Yaml): string
    {
        return self::renderer()->document($data, $body, $format);
    }

    /**
     * The shared default decoder, created on first use.
     */
    private static function decoder(): DecoderInterface
    {
        return self::$decoder ??= new Decoder();
    }

    /**
     * The shared default renderer, created on first use.
     */
    private static function renderer(): RendererInterface
    {
        return self::$renderer ??= new Renderer();
    }

    /**
     * Locate the front matter block. Never throws; cost is proportional to the
     * block size, not the document size.
     *
     * @return array{syntax: Syntax, offset: int, rawStart: int, rawLength: int, length: int}|null
     */
    private static function locate(string $input): ?array
    {
        $offset = str_starts_with($input, self::BOM) ? 3 : 0;
        $len = strlen($input);
        $fence = substr($input, $offset, 3);
        if ('---' !== $fence && '+++' !== $fence) {
            return null;
        }

        $p = $offset + 3;
        $p += strspn($input, " \t", $p);
        if ($p < $len && "\r" === $input[$p] && $p + 1 < $len && "\n" === $input[$p + 1]) {
            ++$p;
        }
        if ($p >= $len || "\n" !== $input[$p]) {
            return null;
        }

        $syntax = '---' === $fence ? Syntax::Yaml : Syntax::Toml;
        $rawStart = $p + 1;
        $pos = $rawStart;
        while ($pos < $len) {
            $blockEnd = self::closeFenceEnd($input, $pos, $len, $syntax);
            if ($blockEnd >= 0) {
                return [
                    'syntax' => $syntax,
                    'offset' => $offset,
                    'rawStart' => $rawStart,
                    'rawLength' => $pos - $rawStart,
                    'length' => $blockEnd - $offset,
                ];
            }
            $nl = strpos($input, "\n", $pos);
            if (false === $nl) {
                break;
            }
            $pos = $nl + 1;
        }

        return null;
    }

    /**
     * Returns the byte offset just after the closing fence line, or -1 when
     * the line at $pos is not a closing fence.
     */
    private static function closeFenceEnd(string $s, int $pos, int $len, Syntax $syntax): int
    {
        $c = $s[$pos];
        if (Syntax::Yaml === $syntax) {
            if ('-' !== $c && '.' !== $c) {
                return -1;
            }
        } elseif ('+' !== $c) {
            return -1;
        }
        if ($pos + 2 >= $len || $s[$pos + 1] !== $c || $s[$pos + 2] !== $c) {
            return -1;
        }
        $q = $pos + 3;
        $q += strspn($s, " \t", $q);
        if ($q >= $len) {
            return $len;
        }
        if ("\r" === $s[$q] && $q + 1 < $len && "\n" === $s[$q + 1]) {
            return $q + 2;
        }
        if ("\n" === $s[$q]) {
            return $q + 1;
        }

        return -1;
    }
}
