<?php

namespace Serfhos\MySearchCrawler\Hook;

use DmitryDulepov\Realurl\Cache\UrlCacheEntry;
use Serfhos\MySearchCrawler\Service\QueueService;
use Serfhos\MySearchCrawler\Service\UrlService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class RealUrlQueueHook
 *
 * @package Serfhos\MySearchCrawler\Hook
 */
class RealUrlQueueEncodedUrlHook implements SingletonInterface
{
    /**
     * @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry
     */
    protected $cacheEntry;
    /**
     * @var QueueService
     */
    protected $queueService;
    /**
     * @var UrlService
     */
    protected $urlService;

    /**
     * Hook from: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['storeInUrlCache']
     *
     * @param array $parameters
     */
    public function storeInUrlCache(array $parameters)
    {
        if ($parameters['cacheEntry'] instanceof UrlCacheEntry) {
            $this->setCacheEntry($parameters['cacheEntry']);
        }
    }

    /**
     * @param \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry
     * @return self
     */
    protected function setCacheEntry(UrlCacheEntry $cacheEntry): self
    {
        $this->cacheEntry = $cacheEntry;
        return $this;
    }

    /**
     * Hook from $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_postProc']
     */
    public function queueCacheEntry(): void
    {
        if ($this->cacheEntry === null) {
            return;
        }

        $url = $this->getUrlService()->generateUrl($this->cacheEntry->getRootPageId(), $this->cacheEntry->getSpeakingUrl());
        if ($this->getUrlService()->shouldIndex($url) === false) {
            return;
        }

        try {
            $this->getQueueService()->enqueue([
                'pid' => $this->cacheEntry->getRootPageId(),
                'crdate' => time(),
                'cruser_id' => $GLOBALS['BE_USER']->user['id'] ?? 0,
                'identifier' => $this->getUrlService()->getHash($url),
                'page_url' => $url,
                'caller' => json_encode(['table' => 'tx_realurl_urldata', 'uid' => $this->cacheEntry->getCacheId()]),
            ]);
        } catch (\Exception $e) {
            // Never throw exceptions in hooks..
            // This should just be skipped if any errors occurred
        }
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\QueueService
     */
    protected function getQueueService(): QueueService
    {
        if ($this->queueService === null) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $this->queueService = $objectManager->get(QueueService::class);
        }
        return $this->queueService;
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
}
