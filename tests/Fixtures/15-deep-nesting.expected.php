<?php

declare(strict_types=1);

return [
    'site' => [
        'theme' => [
            'colors' => ['primary' => '#3366ff', 'secondary' => '#ff6633'],
            'fonts' => ['body' => 'Inter', 'mono' => 'JetBrains Mono'],
        ],
        'features' => ['search' => true, 'comments' => null],
    ],
    'build' => [
        'targets' => [
            ['name' => 'production', 'minify' => true],
            ['name' => 'preview', 'minify' => false],
        ],
    ],
];
