<?php

call_user_func(function ($extension): void {
    // Register main module icon
    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)
        ->registerIcon(
            'module-' . $extension,
            \TYPO3\CMS\Core\Imaging\IconProvider\FontawesomeIconProvider::class,
            ['name' => 'floppy']
        );

    // Register the top-level module
    $name = str_replace('_', '', $extension);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        $name,
        '',
        '',
        null,
        [
            'labels' => 'LLL:EXT:' . $extension . '/Resources/Private/Language/Backend/Module.xlf',
            'name' => $extension,
            'iconIdentifier' => 'module-' . $extension,
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Serfhos.' . $extension,
        $name,
        'Statistics',
        '',
        [
            'Module\Statistics' => 'overview',
            'Module\Index' => 'findByQuery, deleteByQuery, deleteDocument, flushIndex',
        ],
        [
            'access' => 'user, group',
            'icon' => 'EXT:' . $extension . '/Resources/Public/Icons/Statistics.svg',
            'labels' => 'LLL:EXT:' . $extension . '/Resources/Private/Language/Backend/Statistics.xlf',
        ]
    );
}, \Serfhos\MySearchCrawler\Utility\ConfigurationUtility::EXTENSION);
