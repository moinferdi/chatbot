<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    ExtensionManagementUtility::addTcaSelectItem(
        'tt_content',
        'CType',
        [
            'label' => 'LLL:EXT:chatbot/Resources/Private/Language/locallang_db.xlf:CType.chatbot_widget',
            'value' => 'chatbot_widget',
            'icon' => 'extensions-chatbot-widget',
            'group' => 'plugins',
        ]
    );

    $GLOBALS['TCA']['tt_content']['types']['chatbot_widget'] = [
        'showitem' => '
            --palette--;;headers,
            bodytext,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        ',
    ];
})();
