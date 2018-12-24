<?php

namespace Serfhos\MySearchCrawler\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Service: URL handling
 *
 * @package Serfhos\MySearchCrawler\Service
 */
class UrlService
{
    /**
     * @var array
     */
    protected $domains = [];

    /**
     * @param string $url
     * @return bool
     */
    public function shouldIndex(string $url): bool
    {
        // Only index pages without specific types
        if (strpos($url, 'type=') !== false) {
            return false;
        }
        // Avoid paginations
        if (strpos($url, '[page]') !== false) {
            return false;
        }
        // Avoid incorrect url generations
        if (strpos($url, 'cHash') !== false) {
            return false;
        }

        return true;
    }

    /**
     * @param integer $rootPageId
     * @param string $speakingUrl
     * @return string
     */
    public function generateUrl(int $rootPageId, string $speakingUrl): string
    {
        $url = $this->getDomainForRootPageId($rootPageId) . '/' . ltrim($speakingUrl, '/');
        $urlParts = parse_url($url);

        // Avoid excluded parameters for indexing
        parse_str($urlParts['query'], $query);
        if (!empty($query)) {
            foreach (GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['cHashExcludedParameters'], true) as $parameter) {
                unset($query[$parameter]);
            }
            $urlParts['query'] = http_build_query($query);
        }

        return HttpUtility::buildUrl($urlParts);
    }

    /**
     * @param string $url
     * @return string
     */
    public function getHash(string $url): string
    {
        return md5($url);
    }

    /**
     * Find domain from core functionality based on root page ID (and store for local processing)
     *
     * @param int $rootPage
     * @return string
     */
    protected function getDomainForRootPageId(int $rootPage): ?string
    {
        if (!isset($this->domains[$rootPage])) {
            $protocol = GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https' : 'http';
            $domain = BackendUtility::firstDomainRecord(BackendUtility::BEgetRootLine($rootPage))
                ?? GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
            $this->domains[$rootPage] = $protocol . '://' . $domain;
        }
        return $this->domains[$rootPage];
    }
}
