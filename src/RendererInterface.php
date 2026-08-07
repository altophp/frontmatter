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
 * Renders front matter data to a serialization format.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface RendererInterface
{
    /**
     * Render data in the given format (YAML by default).
     *
     * @param array<array-key, mixed> $data
     *
     * @throws Exception\RenderError
     */
    public function render(array $data, RenderFormat $format = RenderFormat::Yaml): string;

    /**
     * Render a full fenced block followed by an optional body.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws Exception\RenderError
     */
    public function document(array $data, string $body = '', RenderFormat $format = RenderFormat::Yaml): string;
}
