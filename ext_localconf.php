<?php

call_user_func(function ($extension): void {
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$extension . '_autocomplete'] =
        \Serfhos\MySearchCrawler\Controller\SearchAPIController::class . '::autocomplete';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$extension . '_search'] =
        \Serfhos\MySearchCrawler\Controller\SearchAPIController::class . '::search';

    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\Container\Container::class)
        ->registerImplementation(
            \Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex::class,
            \Serfhos\MySearchCrawler\Domain\Model\Index\Page::class
        );

    // DataHandlerHook for saving/editing pages
    //$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][$extension] =
    //      \Serfhos\MySearchCrawler\Hook\DataHandlerHook::class;
    //$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][$extension] =
    //      \Serfhos\MySearchCrawler\Hook\DataHandlerHook::class;

    // Check if indexation should be done
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageIndexing'][$extension] =
        \Serfhos\MySearchCrawler\Hook\PageIndexingHook::class;
}, 'my_search_crawler');
