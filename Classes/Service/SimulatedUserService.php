<?php

namespace Serfhos\MySearchCrawler\Service;

use GuzzleHttp\Client;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Frontend\Utility\EidUtility;

/**
 * Service: Simulated Frontend User Session
 */
class SimulatedUserService
{
    protected const INDEX_CONNECTION_TIME_OUT = 15.0;

    /** @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication */
    protected $frontendUserAuthentication;

    /**
     * SimulatedUserService constructor.
     */
    public function __construct()
    {
        // @deprecated should find a new opening to generate FE user for cli commands
        $this->frontendUserAuthentication = EidUtility::initFeUser();
    }

    /**
     * @param  integer  $frontendUserId
     * @return \GuzzleHttp\Client
     */
    public static function createClientForFrontendUser(int $frontendUserId): Client
    {
        $simulatedService = new self();
        $httpOptions = $GLOBALS['TYPO3_CONF_VARS']['HTTP'];
        ArrayUtility::mergeRecursiveWithOverrule($httpOptions, [
            'timeout' => self::INDEX_CONNECTION_TIME_OUT,
            'allow_redirects' => false,
            'verify' => ConfigurationUtility::verify(),
        ]);

        if ($frontendUserId > 0 && $user = $simulatedService->getSessionId($frontendUserId)) {
            $httpOptions['headers']['Cookie'] .= 'fe_typo_user=' . $user . ';';
        }

        return new Client($httpOptions);
    }

    /**
     * @param  integer  $frontendUserId
     * @return string
     */
    public function getSessionId(int $frontendUserId): ?string
    {
        $this->frontendUserAuthentication->logoff();
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
     * Always invoke logoff to clean the possible stored sessions
     */
    public function __destruct()
    {
        $this->frontendUserAuthentication->logoff();
    }
}
