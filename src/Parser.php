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

use Alto\FrontMatter\Exception\SyntaxError;

/**
 * Recursive-descent decoder for the strict YAML 1.2 core subset.
 *
 * Single pass over the raw bytes with an integer cursor. Uses strpos,
 * strspn, strcspn, and direct byte compares only; no preg_* and no AST.
 *
 * @internal
 *
 * @phpstan-import-type FrontMatterValue from DecoderInterface
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class Parser
{
    private const int MAX_DEPTH = 128;
    private const string INT_MAX = '9223372036854775807';
    private const string INT_MIN_ABS = '9223372036854775808';

    /**
     * First bytes that need guard checks in readKey(); everything else is an ordinary key.
     */
    private const string KEY_SPECIAL_FIRST_BYTES = "?&*!@`\"'";

    /**
     * First bytes that need guard checks in parseInlineValue(); everything else is an ordinary scalar.
     */
    private const string VALUE_SPECIAL_FIRST_BYTES = "|>[{\"'&*!%@`?,:-]}";

    private readonly string $src;
    private readonly int $len;

    private bool $eof = false;
    private int $indent = 0;
    private int $start = 0;
    private int $end = 0;
    private int $next = 0;

    private int $fp = 0;
    private string $pendingKey = '';

    private function __construct(string $src)
    {
        $this->src = $src;
        $this->len = strlen($src);
    }

    /**
     * Decode a raw YAML front matter body into a PHP array.
     *
     * @return array<array-key, FrontMatterValue>
     */
    public static function parse(string $yaml): array
    {
        $p = new self($yaml);
        $p->scanFrom(0);
        if ($p->eof) {
            return [];
        }
        $rootIndent = $p->indent;
        $first = $p->src[$p->start];
        $value = '[' === $first || '{' === $first
            ? $p->parseFlow($p->start, 0)
            : ($p->isSequenceEntry()
            ? $p->parseSequence($rootIndent, 0)
            : $p->parseMapping($rootIndent, 0));
        if (!$p->eof) {
            $p->fail('content outside of the document root structure', $p->start);
        }

        return $value;
    }

    // -- line scanning -------------------------------------------------

    /**
     * Positions the cursor on the next content line at or after $pos,
     * skipping blank and full-line comment lines.
     */
    private function scanFrom(int $pos): void
    {
        $s = $this->src;
        $len = $this->len;
        while ($pos < $len) {
            $indent = strspn($s, ' ', $pos);
            $nl = strpos($s, "\n", $pos);
            $lineEnd = false === $nl ? $len : $nl;
            $next = false === $nl ? $len : $nl + 1;
            $contentEnd = $lineEnd;
            if ($contentEnd > $pos && "\r" === $s[$contentEnd - 1]) {
                --$contentEnd;
            }
            $wsEnd = $pos + strspn($s, " \t", $pos, $contentEnd - $pos);
            if ($wsEnd >= $contentEnd) {
                $pos = $next;
                continue;
            }
            $first = $pos + $indent;
            if ("\t" === $s[$first]) {
                $this->fail('tab character in indentation', $first);
            }
            if ('#' === $s[$first]) {
                $pos = $next;
                continue;
            }
            $trimEnd = $contentEnd;
            while ($trimEnd > $first && (' ' === $s[$trimEnd - 1] || "\t" === $s[$trimEnd - 1])) {
                --$trimEnd;
            }
            if (0 === $indent) {
                $c = $s[$first];
                if ('%' === $c) {
                    $this->fail('directives are not supported', $first);
                }
                if (('-' === $c || '.' === $c) && $trimEnd - $first >= 3
                    && $s[$first + 1] === $c && $s[$first + 2] === $c
                    && ($trimEnd - $first === 3 || ' ' === $s[$first + 3] || "\t" === $s[$first + 3])
                ) {
                    $this->fail('multi-document markers are not supported', $first);
                }
            }
            $this->eof = false;
            $this->indent = $indent;
            $this->start = $first;
            $this->end = $trimEnd;
            $this->next = $next;

            return;
        }
        $this->eof = true;
        $this->indent = 0;
        $this->start = $len;
        $this->end = $len;
        $this->next = $len;
    }

    private function advance(): void
    {
        $this->scanFrom($this->next);
    }

    private function isSequenceEntry(): bool
    {
        if ('-' !== $this->src[$this->start]) {
            return false;
        }
        $p = $this->start + 1;

        return $p >= $this->end || ' ' === $this->src[$p] || "\t" === $this->src[$p];
    }

    /**
     * Position of the next colon-with-boundary or hash-with-boundary in
     * [$p, $end), or $end if neither occurs. A colon not followed by
     * space/tab/EOL and a hash not preceded by space/tab are not stops: the
     * scan jumps past them with strcspn() and continues, so cost is
     * O(matches) in C, not O(length) in a userland byte loop.
     */
    private function scanToBoundary(int $p, int $end): int
    {
        $s = $this->src;
        $i = $p;
        while (true) {
            $i += strcspn($s, ':#', $i, $end - $i);
            if ($i >= $end) {
                return $end;
            }
            if ('#' === $s[$i]) {
                if ($i > $p && (' ' === $s[$i - 1] || "\t" === $s[$i - 1])) {
                    return $i;
                }
            } elseif ($i + 1 >= $end || ' ' === $s[$i + 1] || "\t" === $s[$i + 1]) {
                return $i;
            }
            ++$i;
        }
    }

    // -- block structure -----------------------------------------------

    /**
     * @return array<string, FrontMatterValue>
     *
     * @phpstan-impure
     */
    private function parseMapping(int $indent, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            $this->fail('nesting is too deep', $this->start);
        }
        $map = [];
        while (!$this->eof) {
            if ($this->indent < $indent) {
                break;
            }
            if ($this->indent > $indent) {
                $this->fail('bad indentation, expected a key at column ' . ($indent + 1), $this->start);
            }
            if ($this->isSequenceEntry()) {
                $this->fail('unexpected sequence entry inside a mapping', $this->start);
            }
            $keyPos = $this->start;
            $after = $this->readKey();
            $key = $this->pendingKey;
            if (array_key_exists($key, $map)) {
                $this->fail(sprintf('duplicate key "%s"', $key), $keyPos);
            }
            $map[$key] = $this->parseValue($after, $indent, $depth);
        }

        return $map;
    }

    /**
     * @return list<FrontMatterValue>
     *
     * @phpstan-impure
     */
    private function parseSequence(int $indent, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            $this->fail('nesting is too deep', $this->start);
        }
        $s = $this->src;
        $list = [];
        while (!$this->eof && $this->indent === $indent && $this->isSequenceEntry()) {
            $end = $this->end;
            $p = $this->start + 1;
            $p += strspn($s, " \t", $p, max(0, $end - $p));
            if ($p >= $end || '#' === $s[$p]) {
                // Dash alone (or dash plus comment): nested block or null.
                $this->advance();
                if (!$this->eof && $this->indent > $indent) {
                    $list[] = $this->isSequenceEntry()
                        ? $this->parseSequence($this->indent, $depth + 1)
                        : $this->parseMapping($this->indent, $depth + 1);
                } else {
                    $list[] = null;
                }
                continue;
            }
            if ('-' === $s[$p] && ($p + 1 >= $end || ' ' === $s[$p + 1] || "\t" === $s[$p + 1])) {
                $this->fail('nested sequence entries on the same line are not supported', $p);
            }
            if ($this->startsInlineMapping($p)) {
                // The item is a mapping whose indent is the key column.
                $keyColumn = $p - ($this->start - $this->indent);
                $this->indent = $keyColumn;
                $this->start = $p;
                $list[] = $this->parseMapping($keyColumn, $depth + 1);
                continue;
            }
            $list[] = $this->parseInlineValue($p, $indent, $depth);
        }

        return $list;
    }

    /**
     * Reads the mapping key at the cursor into $this->pendingKey and returns
     * the byte position after its ":". Ordinary keys (the common case) skip
     * straight to the boundary scan via a single strcspn() check; only a
     * leading byte in KEY_SPECIAL_FIRST_BYTES pays for the guard checks below.
     */
    private function readKey(): int
    {
        $s = $this->src;
        $p = $this->start;
        $end = $this->end;
        $c = $s[$p];
        if (1 !== strcspn($s, self::KEY_SPECIAL_FIRST_BYTES, $p, 1)) {
            if ('?' === $c && ($p + 1 >= $end || ' ' === $s[$p + 1] || "\t" === $s[$p + 1])) {
                $this->fail('complex keys are not supported', $p);
            }
            if ('&' === $c || '*' === $c) {
                $this->fail('anchors and aliases are not supported', $p);
            }
            if ('!' === $c) {
                $this->fail('tags are not supported', $p);
            }
            if ('@' === $c || '`' === $c) {
                $this->fail(sprintf('the "%s" indicator is reserved', $c), $p);
            }
            if ('"' === $c || "'" === $c) {
                [$key, $q] = $this->readQuoted($p, $end);
                $q += strspn($s, " \t", $q, max(0, $end - $q));
                if ($q >= $end || ':' !== $s[$q]) {
                    $this->fail('expected ":" after the quoted key', min($q, $end));
                }
                if ($q + 1 < $end && ' ' !== $s[$q + 1] && "\t" !== $s[$q + 1]) {
                    $this->fail('expected a space after ":"', $q + 1);
                }
                $this->pendingKey = $key;

                return $q + 1;
            }
        }
        $i = $this->scanToBoundary($p, $end);
        if ($i < $end && ':' === $s[$i]) {
            $key = rtrim(substr($s, $p, $i - $p), " \t");
            if ('' === $key) {
                $this->fail('empty mapping key', $p);
            }
            if ('<<' === $key) {
                $this->fail('merge keys are not supported', $p);
            }
            $this->pendingKey = $key;

            return $i + 1;
        }
        $this->fail('expected a mapping key followed by ":"', $p);
    }

    /**
     * @return FrontMatterValue
     */
    private function parseValue(int $p, int $parentIndent, int $depth): mixed
    {
        $s = $this->src;
        $end = $this->end;
        $p += strspn($s, " \t", $p, max(0, $end - $p));
        if ($p >= $end || '#' === $s[$p]) {
            // The value lives on the following lines, or is null.
            $this->advance();
            if (!$this->eof && $this->indent > $parentIndent) {
                return $this->isSequenceEntry()
                    ? $this->parseSequence($this->indent, $depth + 1)
                    : $this->parseMapping($this->indent, $depth + 1);
            }
            if (!$this->eof && $this->indent === $parentIndent && $this->isSequenceEntry()) {
                // Common front matter style: sequence at the key's own indent.
                return $this->parseSequence($parentIndent, $depth + 1);
            }

            return null;
        }

        return $this->parseInlineValue($p, $parentIndent, $depth);
    }

    /**
     * @return FrontMatterValue
     */
    private function parseInlineValue(int $p, int $parentIndent, int $depth): mixed
    {
        $s = $this->src;
        $end = $this->end;
        $c = $s[$p];
        if (1 !== strcspn($s, self::VALUE_SPECIAL_FIRST_BYTES, $p, 1)) {
            if ('|' === $c || '>' === $c) {
                return $this->parseBlockScalar($p, $parentIndent);
            }
            if ('[' === $c || '{' === $c) {
                return $this->parseFlow($p, $depth);
            }
            if ('"' === $c || "'" === $c) {
                [$str, $q] = $this->readQuoted($p, $end);
                $this->assertLineTail($q);
                $this->advance();

                return $str;
            }
            if ('&' === $c || '*' === $c) {
                $this->fail('anchors and aliases are not supported', $p);
            }
            if ('!' === $c) {
                $this->fail('tags are not supported', $p);
            }
            if ('%' === $c) {
                $this->fail('directives are not supported', $p);
            }
            if ('@' === $c || '`' === $c) {
                $this->fail(sprintf('the "%s" indicator is reserved', $c), $p);
            }
            if ('?' === $c && ($p + 1 >= $end || ' ' === $s[$p + 1] || "\t" === $s[$p + 1])) {
                $this->fail('complex keys are not supported', $p);
            }
            if (('-' === $c || ':' === $c) && ($p + 1 >= $end || ' ' === $s[$p + 1] || "\t" === $s[$p + 1])) {
                $this->fail(sprintf('"%s" is not allowed at the start of a plain scalar', $c), $p);
            }
            if (',' === $c || ']' === $c || '}' === $c) {
                $this->fail(sprintf('unexpected "%s"', $c), $p);
            }
        }

        // Plain scalar to end of line (or to an end-of-line comment).
        $i = $this->scanToBoundary($p, $end);
        if ($i < $end && ':' === $s[$i]) {
            $this->fail('mapping values are not allowed inside a plain scalar, quote the value', $i);
        }
        $text = rtrim(substr($s, $p, $i - $p), " \t");
        $value = $this->typePlain($text, $p);
        $this->advance();

        return $value;
    }

    /**
     * Detects "key: value" at $p on the current line (sequence item shorthand).
     */
    private function startsInlineMapping(int $p): bool
    {
        $s = $this->src;
        $end = $this->end;
        $c = $s[$p];
        if ('[' === $c || '{' === $c || '|' === $c || '>' === $c) {
            return false;
        }
        if ('"' === $c || "'" === $c) {
            // Scan past the quoted scalar without decoding it.
            $i = $p + 1;
            while ($i < $end) {
                if ($s[$i] === $c) {
                    if ("'" === $c && $i + 1 < $end && "'" === $s[$i + 1]) {
                        $i += 2;
                        continue;
                    }
                    ++$i;
                    $i += strspn($s, " \t", $i, max(0, $end - $i));

                    return $i < $end && ':' === $s[$i]
                        && ($i + 1 >= $end || ' ' === $s[$i + 1] || "\t" === $s[$i + 1]);
                }
                if ('"' === $c && '\\' === $s[$i]) {
                    ++$i;
                }
                ++$i;
            }

            return false;
        }
        $i = $this->scanToBoundary($p, $end);

        return $i < $end && ':' === $s[$i];
    }

    /**
     * Rest of the current line after $p must be whitespace or a comment.
     */
    private function assertLineTail(int $p): void
    {
        $s = $this->src;
        $end = $this->end;
        $p += strspn($s, " \t", $p, max(0, $end - $p));
        if ($p < $end && '#' !== $s[$p]) {
            $this->fail('unexpected content after the value', $p);
        }
    }

    // -- scalars ---------------------------------------------------------

    /**
     * Reads a quoted scalar starting at $p (a quote char), ending before $end.
     *
     * @return array{string, int} the decoded string and the position after the closing quote
     */
    private function readQuoted(int $p, int $end): array
    {
        $s = $this->src;
        $q = $s[$p];
        $i = $p + 1;
        $out = '';
        $chunk = $i;
        if ("'" === $q) {
            while ($i < $end) {
                if ("'" === $s[$i]) {
                    if ($i + 1 < $end && "'" === $s[$i + 1]) {
                        $out .= substr($s, $chunk, $i + 1 - $chunk);
                        $i += 2;
                        $chunk = $i;
                        continue;
                    }

                    return [$out . substr($s, $chunk, $i - $chunk), $i + 1];
                }
                ++$i;
            }
            $this->fail('unterminated single-quoted scalar (multi-line quoted scalars are not supported)', $p);
        }
        while ($i < $end) {
            $ch = $s[$i];
            if ('"' === $ch) {
                return [$out . substr($s, $chunk, $i - $chunk), $i + 1];
            }
            if ('\\' !== $ch) {
                ++$i;
                continue;
            }
            $out .= substr($s, $chunk, $i - $chunk);
            ++$i;
            if ($i >= $end) {
                $this->fail('dangling escape at the end of the line', $i - 1);
            }
            $e = $s[$i];
            if ('u' === $e) {
                $out .= $this->readUnicodeEscape($i, $end, $i);
            } else {
                $out .= match ($e) {
                    '\\' => '\\',
                    '"' => '"',
                    '/' => '/',
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    '0' => "\0",
                    'b' => "\x08",
                    'f' => "\x0C",
                    default => $this->fail(sprintf('unsupported escape "\\%s"', $e), $i - 1),
                };
                ++$i;
            }
            $chunk = $i;
        }
        $this->fail('unterminated double-quoted scalar (multi-line quoted scalars are not supported)', $p);
    }

    /**
     * Decodes \uXXXX at $i (pointing at "u"), combining surrogate pairs.
     * Sets $after to the position after the consumed escape sequence.
     */
    private function readUnicodeEscape(int $i, int $end, int &$after): string
    {
        $cp = $this->readHex4($i + 1, $end);
        $after = $i + 5;
        if ($cp >= 0xD800 && $cp <= 0xDBFF) {
            $s = $this->src;
            if ($after + 5 < $end && '\\' === $s[$after] && 'u' === $s[$after + 1]) {
                $low = $this->readHex4($after + 2, $end);
                if ($low >= 0xDC00 && $low <= 0xDFFF) {
                    $cp = 0x10000 + (($cp - 0xD800) << 10) + ($low - 0xDC00);
                    $after += 6;

                    return $this->utf8($cp);
                }
            }
            $this->fail('unpaired UTF-16 surrogate in \\u escape', $i - 1);
        }
        if ($cp >= 0xDC00 && $cp <= 0xDFFF) {
            $this->fail('unpaired UTF-16 surrogate in \\u escape', $i - 1);
        }

        return $this->utf8($cp);
    }

    private function readHex4(int $p, int $end): int
    {
        $s = $this->src;
        if ($p + 4 > $end || 4 !== strspn($s, '0123456789abcdefABCDEF', $p, 4)) {
            $this->fail('expected four hexadecimal digits after \\u', $p);
        }

        return (int) hexdec(substr($s, $p, 4));
    }

    private function utf8(int $cp): string
    {
        if ($cp < 0x80) {
            return \chr($cp & 0x7F);
        }
        if ($cp < 0x800) {
            return \chr(0xC0 | $cp >> 6 & 0x1F) . \chr(0x80 | $cp & 0x3F);
        }
        if ($cp < 0x10000) {
            return \chr(0xE0 | $cp >> 12 & 0x0F) . \chr(0x80 | $cp >> 6 & 0x3F) . \chr(0x80 | $cp & 0x3F);
        }

        return \chr(0xF0 | $cp >> 18 & 0x07) . \chr(0x80 | $cp >> 12 & 0x3F)
            . \chr(0x80 | $cp >> 6 & 0x3F) . \chr(0x80 | $cp & 0x3F);
    }

    /**
     * Types a plain scalar per the YAML 1.2 core schema, reduced.
     */
    private function typePlain(string $t, int $pos): string|int|float|bool|null
    {
        if ('~' === $t || 'null' === $t || 'Null' === $t || 'NULL' === $t) {
            return null;
        }
        if ('true' === $t || 'True' === $t || 'TRUE' === $t) {
            return true;
        }
        if ('false' === $t || 'False' === $t || 'FALSE' === $t) {
            return false;
        }
        $len = strlen($t);
        $i = 0;
        $c = $t[0];
        if ('+' === $c || '-' === $c) {
            $i = 1;
        }
        if ($i >= $len) {
            return $t;
        }
        if ('.' === $t[$i]) {
            $rest = substr($t, $i);
            if ('.inf' === $rest || '.Inf' === $rest || '.INF' === $rest
                || '.nan' === $rest || '.NaN' === $rest || '.NAN' === $rest) {
                $this->fail('".inf" and ".nan" are not supported', $pos);
            }
            // Possibly ".5" style float.
            $d = strspn($t, '0123456789', $i + 1);
            if (0 === $d) {
                return $t;
            }
            $j = $i + 1 + $d;

            return $j === $len || $this->scanExponent($t, $j) ? (float) $t : $t;
        }
        $d = strspn($t, '0123456789', $i);
        if (0 === $d) {
            return $t;
        }
        if ('0' === $t[$i] && $i + 1 < $len) {
            $x = $t[$i + 1];
            if ('x' === $x || 'X' === $x || 'o' === $x || 'O' === $x) {
                $this->fail('hexadecimal and octal integers are not supported', $pos);
            }
        }
        $j = $i + $d;
        if ($j === $len) {
            return $this->intOrFloat($t, $i, $d);
        }
        if ('.' === $t[$j]) {
            ++$j;
            $j += strspn($t, '0123456789', $j);

            return $j === $len || $this->scanExponent($t, $j) ? (float) $t : $t;
        }

        return $this->scanExponent($t, $j) ? (float) $t : $t;
    }

    /**
     * True when $t from $j to the end is a well-formed exponent part.
     */
    private function scanExponent(string $t, int $j): bool
    {
        $len = strlen($t);
        if ($j >= $len || ('e' !== $t[$j] && 'E' !== $t[$j])) {
            return false;
        }
        ++$j;
        if ($j < $len && ('+' === $t[$j] || '-' === $t[$j])) {
            ++$j;
        }
        $d = strspn($t, '0123456789', $j);

        return $d > 0 && $j + $d === $len;
    }

    private function intOrFloat(string $t, int $digitsAt, int $digitCount): int|float
    {
        if ($digitCount < 19) {
            return (int) $t;
        }
        $digits = ltrim(substr($t, $digitsAt), '0');
        if ('' === $digits) {
            return 0;
        }
        $n = strlen($digits);
        $limit = '-' === $t[0] ? self::INT_MIN_ABS : self::INT_MAX;
        if ($n < 19 || ($n === 19 && $digits <= $limit)) {
            return (int) $t;
        }

        return (float) $t;
    }

    // -- flow collections -------------------------------------------------

    /**
     * @return array<array-key, FrontMatterValue>
     */
    private function parseFlow(int $p, int $depth): array
    {
        $s = $this->src;
        $len = $this->len;
        $this->fp = $p + 1;
        $value = '[' === $s[$p]
            ? $this->flowSequence($depth + 1)
            : $this->flowMapping($depth + 1);
        // The rest of the line where the flow ended must be ws or comment.
        $nl = strpos($s, "\n", $this->fp);
        $lineEnd = false === $nl ? $len : $nl;
        $ce = $lineEnd;
        if ($ce > $this->fp && "\r" === $s[$ce - 1]) {
            --$ce;
        }
        $q = $this->fp + strspn($s, " \t", $this->fp, $ce - $this->fp);
        if ($q < $ce && '#' !== $s[$q]) {
            $this->fail('unexpected content after the flow value', $q);
        }
        $this->scanFrom(false === $nl ? $len : $nl + 1);

        return $value;
    }

    /**
     * @return FrontMatterValue
     */
    private function flowValue(int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            $this->fail('nesting is too deep', $this->fp);
        }
        $this->flowSkipWs();
        $s = $this->src;
        if ($this->fp >= $this->len) {
            $this->fail('unterminated flow collection', $this->fp);
        }
        $c = $s[$this->fp];
        if ('[' === $c) {
            ++$this->fp;

            return $this->flowSequence($depth);
        }
        if ('{' === $c) {
            ++$this->fp;

            return $this->flowMapping($depth);
        }
        if ('"' === $c || "'" === $c) {
            [$str, $q] = $this->readQuoted($this->fp, $this->flowLineEnd());
            $this->fp = $q;

            return $str;
        }
        if ('&' === $c || '*' === $c) {
            $this->fail('anchors and aliases are not supported', $this->fp);
        }
        if ('!' === $c) {
            $this->fail('tags are not supported', $this->fp);
        }
        if ('%' === $c || '@' === $c || '`' === $c) {
            $this->fail(sprintf('the "%s" indicator is not allowed here', $c), $this->fp);
        }
        if ('?' === $c || '-' === $c || ':' === $c) {
            $n = $this->fp + 1 < $this->len ? $s[$this->fp + 1] : "\n";
            if (' ' === $n || "\t" === $n || "\n" === $n || "\r" === $n) {
                if ('?' === $c) {
                    $this->fail('complex keys are not supported', $this->fp);
                }
                $this->fail(sprintf('"%s" is not allowed at the start of a plain scalar', $c), $this->fp);
            }
        }
        $pos = $this->fp;
        $text = $this->scanFlowPlain();
        if ('' === $text) {
            $cur = $this->fp < $this->len ? $s[$this->fp] : 'end of input';
            $this->fail(sprintf('unexpected "%s" in flow collection', $cur), $this->fp);
        }
        if ($this->fp < $this->len && ':' === $s[$this->fp]) {
            $this->fail('single-pair mappings inside flow sequences are not supported', $this->fp);
        }

        return $this->typePlain($text, $pos);
    }

    /**
     * @return list<FrontMatterValue>
     */
    private function flowSequence(int $depth): array
    {
        $s = $this->src;
        $list = [];
        $this->flowSkipWs();
        if ($this->fp < $this->len && ']' === $s[$this->fp]) {
            ++$this->fp;

            return [];
        }
        while (true) {
            $list[] = $this->flowValue($depth + 1);
            $this->flowSkipWs();
            if ($this->fp >= $this->len) {
                $this->fail('unterminated flow sequence, expected "]"', $this->fp);
            }
            $c = $s[$this->fp];
            if (',' === $c) {
                ++$this->fp;
                $this->flowSkipWs();
                if ($this->fp < $this->len && ']' === $s[$this->fp]) {
                    $this->fail('trailing commas are not supported in flow collections', $this->fp - 1);
                }
                continue;
            }
            if (']' === $c) {
                ++$this->fp;

                return $list;
            }
            $this->fail('expected "," or "]" in flow sequence', $this->fp);
        }
    }

    /**
     * @return array<string, FrontMatterValue>
     */
    private function flowMapping(int $depth): array
    {
        $s = $this->src;
        $map = [];
        $this->flowSkipWs();
        if ($this->fp < $this->len && '}' === $s[$this->fp]) {
            ++$this->fp;

            return [];
        }
        while (true) {
            $this->flowSkipWs();
            if ($this->fp >= $this->len) {
                $this->fail('unterminated flow mapping, expected "}"', $this->fp);
            }
            $keyPos = $this->fp;
            $c = $s[$this->fp];
            if ('"' === $c || "'" === $c) {
                [$key, $q] = $this->readQuoted($this->fp, $this->flowLineEnd());
                $this->fp = $q;
                $this->flowSkipWs();
                if ($this->fp >= $this->len || ':' !== $s[$this->fp]) {
                    $this->fail('expected ":" after the quoted key', $this->fp);
                }
                ++$this->fp;
            } else {
                if ('&' === $c || '*' === $c || '!' === $c || '?' === $c) {
                    $this->fail('unsupported construct in flow mapping key', $this->fp);
                }
                $key = $this->scanFlowPlain();
                if ('' === $key) {
                    $this->fail('empty key in flow mapping', $this->fp);
                }
                if ($this->fp < $this->len && ':' === $s[$this->fp]) {
                    ++$this->fp;
                } else {
                    $this->flowSkipWs();
                    if ($this->fp < $this->len && ':' === $s[$this->fp]) {
                        ++$this->fp;
                    } elseif ($this->fp < $this->len && (',' === $s[$this->fp] || '}' === $s[$this->fp])) {
                        // Key with no value: {a} means {a: null}.
                        if (array_key_exists($key, $map)) {
                            $this->fail(sprintf('duplicate key "%s"', $key), $keyPos);
                        }
                        $map[$key] = null;
                        if (',' === $s[$this->fp]) {
                            ++$this->fp;
                            $this->flowSkipWs();
                            if ($this->fp < $this->len && '}' === $s[$this->fp]) {
                                $this->fail('trailing commas are not supported in flow collections', $this->fp - 1);
                            }
                            continue;
                        }
                        ++$this->fp;

                        return $map;
                    } else {
                        $this->fail('expected ":" in flow mapping', $this->fp);
                    }
                }
            }
            if (array_key_exists($key, $map)) {
                $this->fail(sprintf('duplicate key "%s"', $key), $keyPos);
            }
            $map[$key] = $this->flowValue($depth + 1);
            $this->flowSkipWs();
            if ($this->fp >= $this->len) {
                $this->fail('unterminated flow mapping, expected "}"', $this->fp);
            }
            $c = $s[$this->fp];
            if (',' === $c) {
                ++$this->fp;
                $this->flowSkipWs();
                if ($this->fp < $this->len && '}' === $s[$this->fp]) {
                    $this->fail('trailing commas are not supported in flow collections', $this->fp - 1);
                }
                continue;
            }
            if ('}' === $c) {
                ++$this->fp;

                return $map;
            }
            $this->fail('expected "," or "}" in flow mapping', $this->fp);
        }
    }

    /**
     * Plain scalar inside flow context. Stops at "," "]" "}" newline,
     * a ws-preceded "#", or a ":" followed by a delimiter. Leaves the
     * cursor on the stop character.
     */
    private function scanFlowPlain(): string
    {
        $s = $this->src;
        $len = $this->len;
        $start = $this->fp;
        $i = $start;
        while (true) {
            $i += strcspn($s, ",]}\n:#{[", $i, $len - $i);
            if ($i >= $len) {
                break;
            }
            $ch = $s[$i];
            if (',' === $ch || ']' === $ch || '}' === $ch || "\n" === $ch) {
                break;
            }
            if ('[' === $ch || '{' === $ch) {
                $this->fail(sprintf('unexpected "%s" inside a plain scalar', $ch), $i);
            }
            if ('#' === $ch) {
                if ($i > $start && (' ' === $s[$i - 1] || "\t" === $s[$i - 1])) {
                    break;
                }
                ++$i;
                continue;
            }
            // ':'
            $n = $i + 1 < $len ? $s[$i + 1] : ' ';
            if (' ' === $n || "\t" === $n || "\n" === $n || "\r" === $n
                || ',' === $n || ']' === $n || '}' === $n) {
                break;
            }
            ++$i;
        }
        $this->fp = $i;

        return rtrim(substr($s, $start, $i - $start), " \t\r");
    }

    private function flowSkipWs(): void
    {
        $s = $this->src;
        $len = $this->len;
        $i = $this->fp;
        while ($i < $len) {
            $c = $s[$i];
            if (' ' === $c || "\t" === $c || "\r" === $c || "\n" === $c) {
                ++$i;
                continue;
            }
            if ('#' === $c && $i > 0 && (' ' === $s[$i - 1] || "\t" === $s[$i - 1] || "\n" === $s[$i - 1] || "\r" === $s[$i - 1])) {
                $nl = strpos($s, "\n", $i);
                $i = false === $nl ? $len : $nl;
                continue;
            }
            break;
        }
        $this->fp = $i;
    }

    /**
     * End of the physical line the flow cursor is on (used to bound quoted scalars).
     */
    private function flowLineEnd(): int
    {
        $nl = strpos($this->src, "\n", $this->fp);
        $end = false === $nl ? $this->len : $nl;
        if ($end > $this->fp && "\r" === $this->src[$end - 1]) {
            --$end;
        }

        return $end;
    }

    // -- block scalars ------------------------------------------------------

    private function parseBlockScalar(int $p, int $parentIndent): string
    {
        $s = $this->src;
        $len = $this->len;
        $end = $this->end;
        $style = $s[$p];
        $chomp = '';
        $explicit = 0;
        $i = $p + 1;
        for ($k = 0; $k < 2 && $i < $end; ++$k) {
            $c = $s[$i];
            if ('+' === $c || '-' === $c) {
                if ('' !== $chomp) {
                    $this->fail('duplicate chomping indicator', $i);
                }
                $chomp = $c;
                ++$i;
            } elseif ($c >= '1' && $c <= '9') {
                if ($explicit > 0) {
                    $this->fail('duplicate indentation indicator', $i);
                }
                $explicit = (int) $c;
                ++$i;
            } else {
                break;
            }
        }
        $this->assertLineTail($i);

        $blockIndent = $explicit > 0 ? $parentIndent + $explicit : -1;
        $pos = $this->next;
        /**
         * @var list<string> $lines
         */
        $lines = [];
        $lastHasNewline = true;
        while ($pos < $len) {
            $nl = strpos($s, "\n", $pos);
            $lineEnd = false === $nl ? $len : $nl;
            $next = false === $nl ? $len : $nl + 1;
            $contentEnd = $lineEnd;
            if ($contentEnd > $pos && "\r" === $s[$contentEnd - 1]) {
                --$contentEnd;
            }
            $ind = strspn($s, ' ', $pos, $contentEnd - $pos);
            $blank = $pos + $ind + strspn($s, " \t", $pos + $ind, $contentEnd - $pos - $ind) >= $contentEnd;
            if (!$blank) {
                if ($blockIndent < 0) {
                    if ($ind <= $parentIndent) {
                        break;
                    }
                    $blockIndent = $ind;
                } elseif ($ind < $blockIndent) {
                    if ($ind <= $parentIndent) {
                        break;
                    }
                    $this->fail('bad indentation inside a block scalar', $pos + $ind);
                }
                $lines[] = substr($s, $pos + $blockIndent, $contentEnd - $pos - $blockIndent);
            } else {
                $lines[] = '';
            }
            $lastHasNewline = false !== $nl;
            $pos = $next;
        }

        $n = \count($lines);
        $trail = 0;
        while ($n > 0 && '' === $lines[$n - 1]) {
            --$n;
            ++$trail;
        }
        if (0 === $n) {
            $this->scanFrom($pos);
            if ('+' === $chomp && $trail > 0) {
                return str_repeat("\n", $trail - ($lastHasNewline ? 0 : 1));
            }

            return '';
        }
        $body = '|' === $style
            ? implode("\n", \array_slice($lines, 0, $n))
            : $this->fold(\array_slice($lines, 0, $n));
        $breaksAfterBody = 1 + $trail - ($lastHasNewline ? 0 : 1);
        $tail = match ($chomp) {
            '-' => '',
            '+' => str_repeat("\n", $breaksAfterBody),
            default => $breaksAfterBody > 0 ? "\n" : '',
        };
        $this->scanFrom($pos);

        return $body . $tail;
    }

    /**
     * Folds lines per the ">" block scalar rules: a single break between two
     * non-more-indented lines becomes a space, n breaks become n-1 newlines,
     * and breaks around more-indented lines are preserved.
     *
     * @param list<string> $lines
     */
    private function fold(array $lines): string
    {
        $out = '';
        $started = false;
        $pendingBlanks = 0;
        $prevMore = false;
        foreach ($lines as $line) {
            if ('' === $line) {
                ++$pendingBlanks;
                continue;
            }
            $more = ' ' === $line[0] || "\t" === $line[0];
            if (!$started) {
                $out = str_repeat("\n", $pendingBlanks) . $line;
                $started = true;
            } else {
                $sep = $pendingBlanks > 0
                    ? str_repeat("\n", $pendingBlanks)
                    : ($prevMore || $more ? "\n" : ' ');
                $out .= $sep . $line;
            }
            $prevMore = $more;
            $pendingBlanks = 0;
        }

        return $out;
    }

    // -- errors ---------------------------------------------------------

    private function fail(string $message, int $pos): never
    {
        $pos = min($pos, $this->len);
        $before = substr($this->src, 0, $pos);
        $line = substr_count($before, "\n") + 1;
        $nl = strrpos($before, "\n");
        $column = false === $nl ? $pos + 1 : $pos - $nl;

        throw new SyntaxError(sprintf('%s at line %d, column %d', $message, $line, $column), $line, $column);
    }
}
