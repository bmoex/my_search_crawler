<?php

namespace Serfhos\MySearchCrawler\Hook;

use DmitryDulepov\Realurl\Decoder\UrlDecoder;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Service\UrlService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class RealUrlQueueHook
 *
 * @package Serfhos\MySearchCrawler\Hook
 */
class RealUrlQueueDecodedUrlHook implements SingletonInterface
{
    /**
     * @var UrlService
     */
    protected $urlService;
    /**
     * @var ElasticSearchService
     */
    protected $elasticSearchService;

    /**
     * @var UrlDecoder
     */
    protected $urlDecoder;
    /**
     * @var string
     */
    protected $url;

    /**
     * Hook from $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc']
     *
     * @param array $parameters
     * @param \DmitryDulepov\Realurl\Decoder\UrlDecoder $urlDecoder
     */
    public function storeUrlDecoder(array $parameters, UrlDecoder $urlDecoder): void
    {
        $this->urlDecoder = $urlDecoder;
        $this->url = $this->url ?? $parameters['URL'];
    }

    /**
     * @param array $parameters
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $typoscriptFrontendController
     */
    public function validateIndex(array $parameters, TypoScriptFrontendController $typoscriptFrontendController): void
    {
        if (!$this->urlDecoder && empty($this->url) && $typoscriptFrontendController->no_cache === false) {
            return;
        }

        return; // @TODO remove index + realurl data record to keep this clean!
        $url = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\UrlService
     */
    protected function getUrlService(): UrlService
    {
        if ($this->urlService === null) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $this->urlService = $objectManager->get(UrlService::class);
        }
        return $this->urlService;
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\ElasticSearchService
     */
    protected function getElasticSearchService(): ElasticSearchService
    {
        if ($this->elasticSearchService === null) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $this->elasticSearchService = $objectManager->get(ElasticSearchService::class);
        }
        return $this->elasticSearchService;
    }
}
