<?php

namespace Serfhos\MySearchCrawler\Service;

use TYPO3\CMS\Frontend\Utility\EidUtility;

/**
 * Service: Simulated Frontend User Session
 *
 * @package Serfhos\MySearchCrawler\Service
 */
class SimulatedUserService
{
    protected $frontendUserAuthentication;

    /**
     * SimulatedUserService constructor.
     */
    public function __construct()
    {
        $this->frontendUserAuthentication = EidUtility::initFeUser();
    }

    /**
     * @param integer $frontendUserId
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
