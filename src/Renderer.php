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

use Alto\FrontMatter\Exception\RenderError;

/**
 * Renders front matter data back to YAML, JSON, or TOML.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class Renderer implements RendererInterface
{
    private const int JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    /**
     * Render data in the given format (YAML by default).
     *
     * @param array<array-key, mixed> $data
     *
     * @throws RenderError
     */
    public function render(array $data, RenderFormat $format = RenderFormat::Yaml): string
    {
        return match ($format) {
            RenderFormat::Json => $this->json($data),
            RenderFormat::Toml => $this->toml($data),
            RenderFormat::Yaml => $this->yaml($data),
        };
    }

    /**
     * Render a full fenced block followed by an optional body.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws RenderError
     */
    public function document(array $data, string $body = '', RenderFormat $format = RenderFormat::Yaml): string
    {
        $fence = RenderFormat::Toml === $format ? '+++' : '---';

        return $fence . "\n" . $this->render($data, $format) . $fence . "\n" . $body;
    }

    /**
     * Render data as pretty-printed JSON.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws RenderError
     */
    public function json(array $data): string
    {
        try {
            return json_encode($data, self::JSON_FLAGS) . "\n";
        } catch (\JsonException $e) {
            throw new RenderError('Unable to render front matter as JSON: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Render data as the YAML subset.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws RenderError
     */
    public function yaml(array $data): string
    {
        if ([] === $data) {
            return '';
        }

        $lines = [];
        self::appendYaml($lines, $data, 0);

        return implode("\n", $lines) . "\n";
    }

    /**
     * Render data as TOML.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws RenderError
     */
    public function toml(array $data): string
    {
        if ([] === $data) {
            return '';
        }

        $lines = [];
        self::appendToml($lines, [], $data);

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string>           $lines
     * @param array<array-key, mixed> $value
     *
     * @throws RenderError
     */
    private static function appendYaml(array &$lines, array $value, int $indent): void
    {
        $pad = str_repeat(' ', $indent);
        if (array_is_list($value)) {
            foreach ($value as $item) {
                if (\is_array($item)) {
                    if ([] === $item) {
                        $lines[] = $pad . '- []';
                        continue;
                    }
                    $lines[] = $pad . '-';
                    self::appendYaml($lines, $item, $indent + 2);
                    continue;
                }
                $lines[] = $pad . '- ' . self::scalar($item);
            }

            return;
        }

        foreach ($value as $key => $item) {
            $renderedKey = self::string((string) $key);
            if (\is_array($item)) {
                if ([] === $item) {
                    $lines[] = $pad . $renderedKey . ': []';
                    continue;
                }
                $lines[] = $pad . $renderedKey . ':';
                self::appendYaml($lines, $item, $indent + 2);
                continue;
            }
            $lines[] = $pad . $renderedKey . ': ' . self::scalar($item);
        }
    }

    /**
     * @param list<string>            $lines
     * @param list<string>            $path
     * @param array<array-key, mixed> $value
     *
     * @throws RenderError
     */
    private static function appendToml(array &$lines, array $path, array $value): void
    {
        if (array_is_list($value)) {
            throw new RenderError('TOML documents must have a mapping at the root or table level');
        }

        if ([] !== $path) {
            if ([] !== $lines) {
                $lines[] = '';
            }
            $lines[] = '[' . implode('.', array_map(self::string(...), $path)) . ']';
        }

        foreach ($value as $key => $item) {
            if (\is_array($item) && !array_is_list($item)) {
                continue;
            }
            $lines[] = self::string((string) $key) . ' = ' . self::tomlValue($item);
        }

        foreach ($value as $key => $item) {
            if (\is_array($item) && !array_is_list($item)) {
                self::appendToml($lines, [...$path, (string) $key], $item);
            }
        }
    }

    /**
     * @throws RenderError
     */
    private static function tomlValue(mixed $value): string
    {
        if (\is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (\is_array($item)) {
                    throw new RenderError('Nested arrays are not supported by the TOML renderer');
                }
                $items[] = self::tomlValue($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        if (null === $value) {
            throw new RenderError('Null values are not supported by TOML');
        }

        return self::scalar($value);
    }

    /**
     * @throws RenderError
     */
    private static function scalar(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value) => (string) $value,
            \is_float($value) => self::float($value),
            \is_string($value) => self::string($value),
            default => throw new RenderError(sprintf('Unsupported front matter value of type "%s"', get_debug_type($value))),
        };
    }

    /**
     * @throws RenderError
     */
    private static function float(float $value): string
    {
        if (!is_finite($value)) {
            throw new RenderError('Unsupported non-finite float value');
        }

        return json_encode($value, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
    }

    /**
     * @throws RenderError
     */
    private static function string(string $value): string
    {
        try {
            return json_encode($value, self::JSON_FLAGS);
        } catch (\JsonException $e) {
            throw new RenderError('Unable to render front matter string: ' . $e->getMessage(), previous: $e);
        }
    }
}
