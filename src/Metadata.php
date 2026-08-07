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

use Alto\FrontMatter\Exception\UnexpectedTypeError;

/**
 * Read-only typed accessor over decoded front matter, shaped like Symfony's
 * ParameterBag.
 *
 * Same surface (all/keys/has/get, getString/getInt/getFloat/getBoolean/getEnum,
 * count, iterator) with two deliberate differences: it is read-only (no
 * set/remove) and it asserts rather than coerces. Values are already typed by
 * the decoder, so a wrong type throws instead of being cast. Access is by
 * top-level key, no dot-notation; all($key) returns a nested array.
 * sourceOffset()/sourceLength() locate the original block.
 *
 * @implements \IteratorAggregate<array-key, FrontMatterValue>
 *
 * @phpstan-import-type FrontMatterValue from DecoderInterface
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class Metadata implements \Countable, \IteratorAggregate
{
    /**
     * @param array<array-key, FrontMatterValue> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly int $sourceOffset = 0,
        private readonly int $sourceLength = 0,
    ) {}

    /**
     * Byte offset of the front matter block in the source (0, or 3 after a BOM).
     */
    public function sourceOffset(): int
    {
        return $this->sourceOffset;
    }

    /**
     * Byte length of the block, fences included. The body starts at
     * sourceOffset() + sourceLength(); zero means the source had no front matter.
     */
    public function sourceLength(): int
    {
        return $this->sourceLength;
    }

    /**
     * All values, or the array at $key (throws when that value is not an array).
     *
     * @return ($key is null ? array<array-key, FrontMatterValue> : array<array-key, mixed>)
     */
    public function all(?string $key = null): array
    {
        if (null === $key) {
            return $this->data;
        }
        $value = $this->data[$key] ?? [];
        if (!\is_array($value)) {
            $this->mismatch($key, 'an array', $value);
        }

        return $value;
    }

    /**
     * The top-level keys.
     *
     * @return list<array-key>
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Whether a key is present.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * A raw value, or the default when the key is absent.
     *
     * @param FrontMatterValue $default
     *
     * @return FrontMatterValue
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * A string leaf; ints and floats are cast, other types throw.
     *
     * @return ($default is null ? string|null : string)
     */
    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->data[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        $this->mismatch($key, 'a string', $value);
    }

    /**
     * An integer leaf; any other type throws.
     *
     * @return ($default is null ? int|null : int)
     */
    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->data[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (\is_int($value)) {
            return $value;
        }
        $this->mismatch($key, 'an integer', $value);
    }

    /**
     * A float leaf; integers are widened, any other type throws.
     *
     * @return ($default is null ? float|null : float)
     */
    public function getFloat(string $key, ?float $default = null): ?float
    {
        $value = $this->data[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (\is_int($value)) {
            return (float) $value;
        }
        if (\is_float($value)) {
            return $value;
        }
        $this->mismatch($key, 'a float', $value);
    }

    /**
     * A boolean leaf; any other type throws.
     *
     * @return ($default is null ? bool|null : bool)
     */
    public function getBoolean(string $key, ?bool $default = null): ?bool
    {
        $value = $this->data[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (\is_bool($value)) {
            return $value;
        }
        $this->mismatch($key, 'a boolean', $value);
    }

    /**
     * A backed-enum case from a string or int leaf; an invalid value throws.
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $class
     * @param T|null          $default
     *
     * @return T|null
     */
    public function getEnum(string $key, string $class, ?\BackedEnum $default = null): ?\BackedEnum
    {
        $value = $this->data[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (!\is_int($value) && !\is_string($value)) {
            $this->mismatch($key, sprintf('a backing value for %s', $class), $value);
        }
        $backingType = (new \ReflectionEnum($class))->getBackingType()?->getName();
        if (('int' === $backingType && !\is_int($value)) || ('string' === $backingType && !\is_string($value))) {
            $this->mismatch($key, sprintf('a %s backing value for %s', $backingType, $class), $value);
        }
        $case = $class::tryFrom($value);
        if (null === $case) {
            throw new UnexpectedTypeError(sprintf('Front matter key "%s" is not a valid case of %s', $key, $class));
        }

        return $case;
    }

    /**
     * A date parsed from a string leaf (ISO 8601), or the default when absent.
     */
    public function getDate(string $key, ?\DateTimeImmutable $default = null): ?\DateTimeImmutable
    {
        $value = $this->data[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (!\is_string($value)) {
            $this->mismatch($key, 'a date string', $value);
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\DateMalformedStringException $e) {
            throw new UnexpectedTypeError(sprintf('Front matter key "%s" is not a parsable date: "%s"', $key, $value), previous: $e);
        }
    }

    public function count(): int
    {
        return \count($this->data);
    }

    /**
     * @return \ArrayIterator<array-key, FrontMatterValue>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    private function mismatch(string $key, string $expected, mixed $value): never
    {
        throw new UnexpectedTypeError(sprintf('Front matter key "%s" is %s, expected %s', $key, get_debug_type($value), $expected));
    }
}
