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

namespace Alto\FrontMatter\Exception;

/**
 * Thrown when the front matter body violates the YAML subset grammar.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class SyntaxError extends \RuntimeException implements FrontMatterExceptionInterface
{
    /**
     * @param int $lineNo   1-based line number within the raw front matter text
     * @param int $columnNo 1-based byte column within that line
     */
    public function __construct(
        string $message,
        private readonly int $lineNo,
        private readonly int $columnNo,
    ) {
        parent::__construct($message);
    }

    /**
     * 1-based line number within the raw front matter text. Not to be
     * confused with getLine(), which is the PHP throw location.
     */
    public function line(): int
    {
        return $this->lineNo;
    }

    /**
     * 1-based byte column within line().
     */
    public function column(): int
    {
        return $this->columnNo;
    }
}
