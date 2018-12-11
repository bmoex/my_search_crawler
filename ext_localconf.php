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
}, \Serfhos\MySearchCrawler\Utility\ConfigurationUtility::EXTENSION);
