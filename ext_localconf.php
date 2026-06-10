<?php

defined('TYPO3') or die();

// Register rate limiter cache.
// Deliberately NOT in the 'pages' or 'all' groups: a backend "clear cache" must
// not reset rate-limit counters (that would make the limit trivially bypassable).
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['chatbot_ratelimit'] ??= [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
    'options' => [
        'defaultLifetime' => 120,
    ],
];
