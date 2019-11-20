<?php

namespace Serfhos\MySearchCrawler\Hook;

use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Service\UrlService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class UrlIndexationHook implements SingletonInterface
{
    /** @var \Serfhos\MySearchCrawler\Service\UrlService */
    protected $urlService;

    /** @var \Serfhos\MySearchCrawler\Service\ElasticSearchService */
    protected $elasticSearchService;

    /**
     * @param  array  $parameters
     * @param  TypoScriptFrontendController  $reference
     * @see \TYPO3\CMS\Frontend\Http\RequestHandler::handle via
     *     $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_eofe']
     */
    public function validate(array $parameters, TypoScriptFrontendController $reference): void
    {
        return; // @TODO add to queue
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
