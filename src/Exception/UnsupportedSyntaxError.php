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
 * Thrown when decoding a front matter syntax that has no decoder, such as TOML.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class UnsupportedSyntaxError extends \RuntimeException implements FrontMatterExceptionInterface {}
