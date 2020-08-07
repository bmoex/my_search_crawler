<?php

namespace Serfhos\MySearchCrawler\Service;

use GuzzleHttp\Client;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Service: Simulated Frontend User Session
 */
class SimulatedUserService
{
    protected const INDEX_CONNECTION_TIME_OUT = 15.0;

    /** @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication */
    protected $frontendUserAuthentication;

    /**
     * @param  \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication|null  $frontendUserAuthentication
     */
    public function __construct(?FrontendUserAuthentication $frontendUserAuthentication = null)
    {
        if ($frontendUserAuthentication === null) {
            // @deprecated should find a new opening to generate FE user for cli commands
            $frontendUserAuthentication = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            $frontendUserAuthentication->start();
            $frontendUserAuthentication->unpack_uc();
        }

        $this->frontendUserAuthentication = $frontendUserAuthentication;
    }

    /**
     * @param  int  $frontendUserId
     * @return \GuzzleHttp\Client
     */
    public function createClientForFrontendUser(int $frontendUserId): Client
    {
        $httpOptions = $GLOBALS['TYPO3_CONF_VARS']['HTTP'];
        ArrayUtility::mergeRecursiveWithOverrule($httpOptions, [
            'timeout' => self::INDEX_CONNECTION_TIME_OUT,
            'allow_redirects' => false,
            'verify' => ConfigurationUtility::verify(),
        ]);

        if ($frontendUserId > 0 && $user = $this->getSessionId($frontendUserId)) {
            $httpOptions['headers']['Cookie'] .= 'fe_typo_user=' . $user . ';';
        }

        return new Client($httpOptions);
    }

    /**
     * @param  int  $frontendUserId
     * @return string
     */
    public function getSessionId(int $frontendUserId): ?string
    {
        $user = $this->frontendUserAuthentication->getRawUserByUid($frontendUserId);
        if (!empty($user)) {
            // Force disabled IP lock on this created user session
            $user['disableIPlock'] = true;
            $session = $this->frontendUserAuthentication->createUserSession($user);

            return $session['ses_id'] ?? null;
        }

        return null;
    }

    /**
     * Always invoke close to clean the possible stored sessions
     */
    public function __destruct()
    {
        $this->frontendUserAuthentication->logoff();
    }
}
