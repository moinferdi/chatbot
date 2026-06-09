<?php

declare(strict_types=1);

return [
    'frontend' => [
        'moinferdi/chatbot/proxy' => [
            'target' => \Moinferdi\Chatbot\Middleware\ChatProxyMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
