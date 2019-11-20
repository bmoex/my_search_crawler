<?php

namespace Serfhos\MySearchCrawler\Service;

use GuzzleHttp\Client;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;

class ClientService
{
    protected const INDEX_CONNECTION_TIME_OUT = 15.0;

    /** @var \Serfhos\MySearchCrawler\Service\SimulatedUserService */
    protected $simulatedUserService;

    /**
     * @param  integer  $frontendUserId
     * @return \GuzzleHttp\Client
     */
    public function createForFrontendUser(int $frontendUserId): Client
    {
        $httpOptions = $GLOBALS['TYPO3_CONF_VARS']['HTTP'];
        ArrayUtility::mergeRecursiveWithOverrule($httpOptions, [
            'timeout' => self::INDEX_CONNECTION_TIME_OUT,
            'allow_redirects' => false,
            'verify' => ConfigurationUtility::verify(),
        ]);

        if ($frontendUserId > 0) {
            if ($user = $this->getSimulatedUserService()->getSessionId($frontendUserId)) {
                $httpOptions['headers']['Cookie'] .= 'fe_typo_user=' . $user . ';';
            }
        }

        return new Client($httpOptions);
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\SimulatedUserService
     */
    protected function getSimulatedUserService(): SimulatedUserService
    {
        return $this->simulatedUserService = $this->simulatedUserService ?? new SimulatedUserService();
    }
}
