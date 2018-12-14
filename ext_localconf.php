<?php
call_user_func(function ($extension) {
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$extension . '_autocomplete'] = \Serfhos\MySearchCrawler\Controller\SearchAPIController::class . '::autocomplete';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][$extension . '_search'] = \Serfhos\MySearchCrawler\Controller\SearchAPIController::class . '::search';

    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\Container\Container::class)
        ->registerImplementation(
            \Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex::class,
            \Serfhos\MySearchCrawler\Domain\Model\Index\Page::class
        );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][$extension] = \Serfhos\MySearchCrawler\Console\Command\SearchCrawlerCommandController::class;

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'][$extension] = \Serfhos\MySearchCrawler\Hook\RealUrlQueueDecodedUrlHook::class . '->storeUrlDecoder';
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_eofe'][$extension] = \Serfhos\MySearchCrawler\Hook\RealUrlQueueDecodedUrlHook::class . '->validateIndex';
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['storeInUrlCache'][$extension] = \Serfhos\MySearchCrawler\Hook\RealUrlQueueEncodedUrlHook::class . '->storeInUrlCache';
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_postProc'][$extension] = \Serfhos\MySearchCrawler\Hook\RealUrlQueueEncodedUrlHook::class . '->queueCacheEntry';
}, \Serfhos\MySearchCrawler\Utility\ConfigurationUtility::EXTENSION);
