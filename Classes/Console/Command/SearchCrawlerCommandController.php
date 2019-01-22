<?php
declare(strict_types=1);

namespace Serfhos\MySearchCrawler\Console\Command;

use DateTime;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use GuzzleHttp\Client;
use Serfhos\MySearchCrawler\Exception\ShouldIndexException;
use Serfhos\MySearchCrawler\Request\CrawlerWebRequest;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Service\QueueService;
use Serfhos\MySearchCrawler\Service\SimulatedUserService;
use Serfhos\MySearchCrawler\Service\UrlService;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * CommandController: SearchCrawler
 *
 * @package Serfhos\MySearchCrawler\Console\Command
 */
class SearchCrawlerCommandController extends CommandController
{
    protected const INDEX_CONNECTION_TIME_OUT = 15.0;

    /**
     * @var ElasticSearchService
     */
    protected $elasticSearchService;

    /**
     * @var QueueService
     */
    protected $queueService;

    /**
     * @var SimulatedUserService
     */
    protected $simulatedUserService;

    /**
     * @var UrlService
     */
    protected $urlService;

    /**
     * Constructor: CommandController: SearchCrawler
     *
     * @param QueueService $queueService
     * @param ElasticSearchService $elasticSearchService
     * @param SimulatedUserService $simulatedUserService
     * @param UrlService $urlService
     */
    public function __construct(
        QueueService $queueService,
        ElasticSearchService $elasticSearchService,
        SimulatedUserService $simulatedUserService,
        UrlService $urlService
    ) {
        $this->elasticSearchService = $elasticSearchService;
        $this->queueService = $queueService;
        $this->simulatedUserService = $simulatedUserService;
        $this->urlService = $urlService;
    }

    /**
     * Queue all known urls from realurl into the crawler index
     *
     * @return boolean
     */
    public function queueAllKnownUrlsCommand(): bool
    {
        $queued = 0;

        $result = $this->getConnectionForTable('tx_realurl_urldata')->select(
            ['uid', 'rootpage_id', 'speaking_url', 'request_variables'],
            'tx_realurl_urldata',
            [],
            [],
            ['speaking_url' => 'asc']
        );

        $this->output->progressStart($result->rowCount());
        while ($row = $result->fetch()) {
            try {
                // Only crawl default page types
                if (strpos($row['request_variables'], 'type') !== false) {
                    continue;
                }
                // Avoid paginations
                if (strpos($row['request_variables'], '[page]') !== false) {
                    continue;
                }

                $url = $this->urlService->generateUrl($row['rootpage_id'], $row['speaking_url']);
                if ($this->urlService->shouldIndex($url) === false) {
                    continue;
                }

                if ($this->queueService->enqueue([
                    'pid' => $row['rootpage_id'],
                    'crdate' => time(),
                    'cruser_id' => $GLOBALS['BE_USER']->user['id'] ?? 0,
                    'identifier' => $this->urlService->getHash($url),
                    'page_url' => $url,
                    'caller' => json_encode(['table' => 'tx_realurl_urldata', 'uid' => $row['uid'], 'data' => $row]),
                ])) {
                    $queued++;
                }
            } catch (\Exception $e) {
                // If database error, do not break loop..
            }

            $this->output->progressAdvance();
        }
        $this->output->progressFinish();

        $duplicated = $this->queueService->cleanDuplicateQueueEntries();
        $this->outputLine();
        $this->outputLine('Total queued records: ' . $queued);
        $this->outputLine('Total duplicated records removed: ' . $duplicated);

        return true;
    }

    /**
     * @param string $indexedSince
     * @return boolean
     */
    public function requeueIndexedDocumentsCommand(string $indexedSince = null): bool
    {
        if ($indexedSince === null) {
            $indexedSince = 'now-1d/d';
        } else {
            $indexedSince = (new DateTime('@' . strtotime($indexedSince)))->format(DateTime::ATOM);
        }

        $queued = 0;
        $page = 0;
        $size = 50;

        $query = [
            'range' => [
                'indexed' => [
                    'lte' => $indexedSince,
                ],
            ],
        ];
        $this->output->progressStart((int)$this->elasticSearchService->count(['query' => $query])['count']);
        while ($page !== null) {
            $results = $this->elasticSearchService->search([
                'from' => $page * $size,
                'size' => $size,
                'query' => $query,
            ]);
            if (isset($results['hits']['hits']) && !empty($results['hits']['hits'])) {
                foreach ($results['hits']['hits'] as $hit) {
                    try {
                        if ($this->queueService->enqueue([
                            'pid' => 0,
                            'crdate' => time(),
                            'cruser_id' => $GLOBALS['BE_USER']->user['id'] ?? 0,
                            'identifier' => $hit['_id'],
                            'page_url' => $hit['_source']['url'],
                            'caller' => json_encode(['index' => $this->elasticSearchService->getIndex(), 'source' => $hit['_source']]),
                        ])) {
                            $queued++;
                        }
                    } catch (\Exception $e) {
                        // If database error, do not break loop..
                    }
                }
                $page++;
            } else {
                $page = null;
            }
        }
        $this->output->progressFinish();

        $duplicated = $this->queueService->cleanDuplicateQueueEntries();
        $this->outputLine();
        $this->outputLine('Total queued records: ' . $queued);
        $this->outputLine('Total duplicated records removed: ' . $duplicated);

        return true;
    }

    /**
     * Crawl queued records
     *
     * @param integer $limit
     * @param integer $frontendUserId
     * @return boolean
     */
    public function indexQueueCommand($limit = 50, $frontendUserId = 0): bool
    {
        if ($duplicated = $this->queueService->cleanDuplicateQueueEntries()) {
            $this->outputLine('Total duplicated records removed: ' . $duplicated);
        }

        $indexed = 0;
        $client = $this->getClientAsFrontendUser($frontendUserId);

        $this->output->progressStart($limit);
        foreach ($this->queueService->getQueue($limit) as $row) {
            try {
                $request = new CrawlerWebRequest(
                    $client,
                    $row['page_url']
                );

                if ($this->index($request)) {
                    $indexed++;
                }
            } catch (\Exception $e) {
                // This should be handled in code!
                $this->outputLine(date('c') . ':' . get_class($e) . ':' . $e->getCode() . ':' . $e->getMessage());
            }

            // Always dequeue when handled, even if exception is thrown
            $this->queueService->dequeue((int)$row['uid']);

            $this->output->progressAdvance();
        }
        $this->output->progressFinish();

        $this->outputLine('');
        $this->outputLine('Total indexed documents: ' . $indexed);

        return true;
    }

    /**
     * Add crawled url in current indexation
     *
     * @param string $url
     * @param int $frontendUserId
     * @return boolean
     */
    public function indexUrlCommand($url = '', $frontendUserId = 0): bool
    {
        $client = $this->getClientAsFrontendUser($frontendUserId);

        try {
            $request = new CrawlerWebRequest(
                $client,
                $url
            );

            if ($this->index($request, true)) {
                $this->outputLine('Index successfully added (' . $url . ') to index (' . ConfigurationUtility::index() . ')');
            }
        } catch (\Exception $e) {
            // This should be handled in code!
            $this->outputLine(date('c') . ':' . get_class($e) . ':' . $e->getCode() . ':' . $e->getMessage());
        }
        return true;
    }

    /**
     * Flush full elastic search index via command line
     *
     * @return bool
     */
    public function flushIndexCommand(): bool
    {
        $this->elasticSearchService->flush();
        $this->outputLine('Index is flushed!');
        return true;
    }

    /**
     * @param integer $frontendUserId
     * @return Client
     */
    protected function getClientAsFrontendUser(int $frontendUserId): Client
    {
        $httpOptions = $GLOBALS['TYPO3_CONF_VARS']['HTTP'];
        ArrayUtility::mergeRecursiveWithOverrule($httpOptions, [
            'timeout' => self::INDEX_CONNECTION_TIME_OUT,
            'allow_redirects' => false,
            'verify' => ConfigurationUtility::verify(),
        ]);

        if ($frontendUserId > 0) {
            $this->simulatedUserService = $this->simulatedUserService ?? new SimulatedUserService;
            if ($user = $this->simulatedUserService->getSessionId($frontendUserId)) {
                $httpOptions['headers']['Cookie'] .= 'fe_typo_user=' . $user . ';';
            }
        }

        return new Client($httpOptions);
    }

    /**
     * @param string $tableName
     * @return Connection
     */
    public function getConnectionForTable(string $tableName): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
    }

    /**
     * @param \Serfhos\MySearchCrawler\Request\CrawlerWebRequest $request
     * @param bool $throw
     * @return boolean
     * @throws \Serfhos\MySearchCrawler\Exception\ShouldIndexException if $throw is configured
     */
    protected function index(CrawlerWebRequest $request, $throw = false): bool
    {
        $index = $request->getElasticSearchIndex();
        try {
            if ($request->shouldIndex()) {
                $this->elasticSearchService->addDocument($index);
            }
            return true;
        } catch (ShouldIndexException $e) {
            // Always try to delete document!
            try {
                $this->elasticSearchService->removeDocument($index->getIndexIdentifier());
            } catch (ElasticsearchException $_e) {
                // Do nothing..
            }

            if ($throw) {
                throw $e;
            }
        }
        return false;
    }
}
