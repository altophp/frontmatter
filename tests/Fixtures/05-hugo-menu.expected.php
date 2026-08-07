<?php

declare(strict_types=1);

return [
    'title' => 'Documentation',
    'menu' => [
        'main' => ['weight' => 20, 'parent' => 'resources'],
        'footer' => ['weight' => 5],
    ],
    'outputs' => ['HTML', 'RSS'],
    'params' => [
        'toc' => true,
        'editLink' => 'https://example.com/edit',
    ],
];
