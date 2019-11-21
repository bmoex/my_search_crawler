<?php

namespace Serfhos\MySearchCrawler\Service;

use Elasticsearch\Common\Exceptions\ElasticsearchException;
use GuzzleHttp\Client;
use Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex;
use Serfhos\MySearchCrawler\Exception\RequestNotFoundException;
use Serfhos\MySearchCrawler\Exception\ShouldIndexException;
use Serfhos\MySearchCrawler\Request\CrawlerWebRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class CrawlerWebRequestService
{
    /** @var \Serfhos\MySearchCrawler\Service\ElasticSearchService */
    protected $elasticSearchService;

    /** @var \TYPO3\CMS\Extbase\Object\ObjectManager */
    protected $objectManager;

    /**
     * @param  \TYPO3\CMS\Extbase\Object\ObjectManager|null  $objectManager
     * @param  \Serfhos\MySearchCrawler\Service\ElasticSearchService  $elasticSearchService
     */
    public function __construct(
        ?ObjectManager $objectManager = null,
        ?ElasticSearchService $elasticSearchService = null
    ) {
        $this->objectManager = $objectManager ?? GeneralUtility::makeInstance(ObjectManager::class);
        $this->elasticSearchService = $elasticSearchService ?? GeneralUtility::makeInstance(ElasticSearchService::class);
    }

    /**
     * @param  \GuzzleHttp\Client  $client
     * @param  string  $url
     * @param  bool  $throwable
     * @return boolean
     */
    public function crawl(Client $client, string $url, bool $throwable = false): bool
    {
        try {
            $request = new CrawlerWebRequest($client, $url);
            /** @var \Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex $index */
            $index = $this->objectManager->get(ElasticSearchIndex::class, [
                'url' => $request->getUri(),
                'title' => $request->getTitle(),
                'meta' => $request->getMetaTags(),
                'content' => $request->getContent(),
            ]);

            try {
                if ($request->shouldIndex()) {
                    $this->elasticSearchService->addDocument($index);

                    return true;
                }
            } catch (ShouldIndexException $e) {
                $this->removeIndex($index->getIndexIdentifier());

                if ($throwable) {
                    throw $e;
                }
            }
        } catch (RequestNotFoundException $e) {
            $index = $this->objectManager->get(ElasticSearchIndex::class, [
                'url' => $url,
            ]);
            $this->removeIndex($index->getIndexIdentifier());

            if ($throwable) {
                throw $e;
            }
        }

        return false;
    }

    /**
     * Always try to remove the document with try/catch
     *
     * @param  string  $identifier
     * @return bool
     */
    protected function removeIndex(string $identifier): bool
    {
        // Always try to remove the document
        try {
            $this->elasticSearchService->removeDocument($identifier);

            return true;
        } catch (ElasticsearchException $e) {
            // Do nothing!
        }

        return false;
    }
}
