<?php

namespace Serfhos\MySearchCrawler\Hook;

use Serfhos\MySearchCrawler\Service\QueueService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Utility\CanonicalizationUtility;

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
     * @param  \Serfhos\MySearchCrawler\Service\QueueService|null  $queueService
     */
    public function __construct(?QueueService $queueService = null)
    {
        $this->queueService = $queueService ?? GeneralUtility::makeInstance(QueueService::class);
    }

    /**
     * @param  \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController  $reference
     * @return void
     * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::generatePage_postProcessing
     */
    public function hook_indexContent(TypoScriptFrontendController $reference): void
    {
        // Build current url with typolink
        $url = $reference->cObj->typoLink_URL([
            'parameter' => $reference->id . ',' . $reference->type,
            'forceAbsoluteUrl' => true,
            'addQueryString' => true,
            'addQueryString.' => [
                'method' => 'GET',
                'exclude' => implode(
                    ',',
                    CanonicalizationUtility::getParamsToExcludeForCanonicalizedUrl(
                        (int)$reference->id,
                        (array)$GLOBALS['TYPO3_CONF_VARS']['FE']['additionalCanonicalizedUrlParameters']
                    )
                ),
            ],
        ]);

        if (!GeneralUtility::isValidUrl($url)) {
            return;
        }

        $this->queueService->enqueue($url, ['through' => 'PageIndexingHook']);
    }
}
