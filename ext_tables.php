<?php

call_user_func(function ($extension): void {
    // Register the top-level module
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'mysearchcrawler',
        '',
        '',
        null,
        [
            'labels' => 'LLL:EXT:my_search_crawler/Resources/Private/Language/Backend/Module.xlf',
            'name' => $extension,
            'iconIdentifier' => 'module-my_search_crawler',
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Serfhos.' . $extension,
        'mysearchcrawler',
        'Statistics',
        '',
        [
            'Module\Statistics' => 'overview',
            'Module\Index' => 'findByQuery, deleteByQuery, deleteDocument, flushIndex',
        ],
        [
            'access' => 'user, group',
            'icon' => 'EXT:my_search_crawler/Resources/Public/Icons/Statistics.svg',
            'labels' => 'LLL:EXT:my_search_crawler/Resources/Private/Language/Backend/Statistics.xlf',
        ]
    );
}, 'my_search_crawler');
