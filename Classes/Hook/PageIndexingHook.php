<?php

namespace Serfhos\MySearchCrawler\Hook;

use Serfhos\MySearchCrawler\Service\QueueService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

// Ignore sniff for custom hooks from TYPO3 Core
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

/**
 * @see \TYPO3\CMS\Frontend\Http\RequestHandler::handle via TSFE->generatePage_postProcessing => through
 *     $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageIndexing']
 */
class PageIndexingHook implements SingletonInterface
{
    /** @var \Serfhos\MySearchCrawler\Service\QueueService */
    protected $queueService;

    /**
     * @param  \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController  $reference
     * @return void
     * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::generatePage_postProcessing
     */
    public function hook_indexContent(TypoScriptFrontendController $reference): void
    {
        $url = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        if ($url === '') {
            return;
        }

        if (!GeneralUtility::isValidUrl($url)) {
            return;
        }

        $this->getQueueService()->enqueue($url, ['through' => 'PageIndexingHook']);
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\QueueService
     */
    protected function getQueueService(): QueueService
    {
        if ($this->queueService === null) {
            $this->queueService = GeneralUtility::makeInstance(QueueService::class);
        }

        return $this->queueService;
    }
}
