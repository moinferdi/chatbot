<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    $ll = 'LLL:EXT:chatbot/Resources/Private/Language/locallang_db.xlf:';

    $columns = [
        'tx_chatbot_enabled' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_enabled',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [[
                    'label' => '',
                    'labelChecked' => 'Enabled',
                    'labelUnchecked' => 'Disabled',
                ]],
                'default' => 0,
            ],
        ],
        'tx_chatbot_everywhere' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_everywhere',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [[
                    'label' => '',
                    'labelChecked' => 'Enabled',
                    'labelUnchecked' => 'Disabled',
                ]],
                'default' => 0,
            ],
        ],
        'tx_chatbot_base_url' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_base_url',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,required',
                'placeholder' => 'https://your-openwebui.example.com',
                'size' => 40,
                'max' => 255,
            ],
        ],
        'tx_chatbot_model' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_model',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,required',
                'placeholder' => 'gpt-4o',
                'size' => 30,
                'max' => 100,
            ],
        ],
        'tx_chatbot_api_key' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_api_key',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,password',
                'size' => 40,
                'max' => 4096,
                'placeholder' => '%env(CHATBOT_API_KEY)%',
                'default' => '%env(CHATBOT_API_KEY)%',
            ],
        ],
        'tx_chatbot_color_primary' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_color_primary',
            'description' => $ll . 'pages.tx_chatbot_color_primary.description',
            'config' => [
                'type' => 'color',
                'size' => 10,
                'valuePicker' => [
                    'items' => [
                        ['Blue', '#4F9EF7'],
                        ['Indigo', '#6366F1'],
                        ['Purple', '#A855F7'],
                        ['Pink', '#EC4899'],
                        ['Green', '#22C55E'],
                        ['Orange', '#F97316'],
                        ['Red', '#EF4444'],
                        ['Slate', '#0F172A'],
                        ['Black', '#000000'],
                    ],
                ],
                'default' => '#4F9EF7',
            ],
        ],
        'tx_chatbot_color_text' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_color_text',
            'description' => $ll . 'pages.tx_chatbot_color_text.description',
            'config' => [
                'type' => 'color',
                'size' => 10,
                'default' => '#ffffff',
            ],
        ],
        'tx_chatbot_color_user_text' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_color_user_text',
            'description' => $ll . 'pages.tx_chatbot_color_user_text.description',
            'config' => [
                'type' => 'color',
                'size' => 10,
                'default' => '#1a1a1a',
            ],
        ],
        'tx_chatbot_color_outline' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_color_outline',
            'description' => $ll . 'pages.tx_chatbot_color_outline.description',
            'config' => [
                'type' => 'color',
                'size' => 10,
                'default' => '#ffffff',
            ],
        ],
        'tx_chatbot_position' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_position',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'pages.tx_chatbot_position.bottom-right', 'value' => 'bottom-right'],
                    ['label' => $ll . 'pages.tx_chatbot_position.bottom-left', 'value' => 'bottom-left'],
                ],
                'default' => 'bottom-right',
            ],
        ],
        'tx_chatbot_offset_x' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_offset_x',
            'description' => $ll . 'pages.tx_chatbot_offset_x.description',
            'config' => [
                'type' => 'number',
                'range' => ['lower' => 0, 'upper' => 100],
                'default' => 0,
                'size' => 5,
            ],
        ],
        'tx_chatbot_offset_y' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_offset_y',
            'description' => $ll . 'pages.tx_chatbot_offset_y.description',
            'config' => [
                'type' => 'number',
                'range' => ['lower' => 0, 'upper' => 100],
                'default' => 0,
                'size' => 5,
            ],
        ],
        'tx_chatbot_start_message' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_start_message',
            'description' => $ll . 'pages.tx_chatbot_start_message.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
                'max' => 500,
                'placeholder' => 'Hello! How can I help you today?',
            ],
        ],
        'tx_chatbot_title' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_title',
            'description' => $ll . 'pages.tx_chatbot_title.description',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'size' => 40,
                'max' => 255,
                'placeholder' => 'Chat Assistant',
            ],
        ],
        'tx_chatbot_avatar' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_chatbot_avatar',
            'config' => [
                'type' => 'file',
                'allowed' => ['common-image-types'],
                'maxitems' => 1,
            ],
        ],
    ];

    foreach ($columns as $field => $config) {
        if (isset($GLOBALS['TCA']['pages']['columns'][$field])) {
            $GLOBALS['TCA']['pages']['columns'][$field] = $config;
        } else {
            ExtensionManagementUtility::addTCAcolumns('pages', [$field => $config]);
        }
    }

    $chatbotFields = implode(',', [
        '--div--;' . $ll . 'pages.tab.chatbot',
        'tx_chatbot_enabled', 'tx_chatbot_everywhere',
        'tx_chatbot_base_url', 'tx_chatbot_model', 'tx_chatbot_api_key',
        'tx_chatbot_color_primary', 'tx_chatbot_color_text', 'tx_chatbot_color_user_text', 'tx_chatbot_color_outline',
        'tx_chatbot_position', 'tx_chatbot_offset_x', 'tx_chatbot_offset_y', 'tx_chatbot_start_message',
        'tx_chatbot_title', 'tx_chatbot_avatar',
    ]);

    ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        $chatbotFields,
        '',
        'after:--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access'
    );

    foreach (array_keys($columns) as $field) {
        $GLOBALS['TCA']['pages']['columns'][$field]['displayCond'] = 'FIELD:is_siteroot:=:1';
    }

    // Connection settings are site-wide and shared across all languages; only the
    // start message is per-language. l10n_mode=exclude makes a translated root page
    // inherit the default-language value instead of overwriting it with a blank
    // (which previously could wipe out the API key in non-default languages).
    foreach (array_keys($columns) as $field) {
        if ($field !== 'tx_chatbot_start_message' && $field !== 'tx_chatbot_title') {
            $GLOBALS['TCA']['pages']['columns'][$field]['l10n_mode'] = 'exclude';
        }
    }
})();
