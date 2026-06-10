<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

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

// Fallback TypoScript loading — ensures content-element rendering and the
// global widget injection work even when the site does not declare
// `moinferdi/chatbot` as a dependency in its site-set config.yaml.
// The Site Set in Configuration/Sets/Chatbot/ is the canonical source;
// this import keeps old projects compatible.
ExtensionManagementUtility::addTypoScriptSetup('
    @import "EXT:chatbot/Configuration/Sets/Chatbot/setup.typoscript"
');
