<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Chatbot',
    'description' => 'LLM chatbot widget with OpenWebUI integration. BYOK, proxy-backed, themeable, multi-language aware.',
    'category' => 'plugin',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'fluid_styled_content' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
