<?php

namespace Serfhos\MySearchCrawler\Hook;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class UrlIndexationHook implements SingletonInterface
{

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

}
