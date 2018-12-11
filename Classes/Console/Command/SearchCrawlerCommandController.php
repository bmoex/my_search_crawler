<?php
declare(strict_types=1);

namespace Serfhos\MySearchCrawler\Console\Command;

use GuzzleHttp\Client;
use Serfhos\MySearchCrawler\Exception\NotImplementedException;
use Serfhos\MySearchCrawler\Exception\RequestNotFoundException;
use Serfhos\MySearchCrawler\Request\CrawlerWebRequest;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Service\QueueService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
     * @var array
     */
    protected $domains = [];

    /**
     * Constructor: CommandController: SearchCrawler
     *
     * @param QueueService $queueService
     * @param ElasticSearchService $elasticSearchService
     */
    public function __construct(QueueService $queueService, ElasticSearchService $elasticSearchService)
    {
        $this->elasticSearchService = $elasticSearchService;
        $this->queueService = $queueService;
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

                $url = $this->getDomainForRootPageId($row['rootpage_id']) . '/' . ltrim($row['speaking_url'], '/');

                if ($this->queueService->enqueue([
                    'pid' => $row['rootpage_id'],
                    'crdate' => time(),
                    'cruser_id' => $GLOBALS['BE_USER']->id,
                    'identifier' => md5($url),
                    'page_url' => $url,
                    'caller' => json_encode(['table' => 'tx_realurl_urldata', 'uid' => $row['uid'], 'data' => $row])
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
        $this->outputLine('');
        $this->outputLine('Total queued records: ' . $queued);
        $this->outputLine('Total duplicated records removed: ' . $duplicated);

        return true;
    }

    /**
     * Crawl queued records
     *
     * @param integer $limit
     * @return boolean
     */
    public function indexQueueCommand($limit = 50): bool
    {
        $indexed = 0;
        $client = new Client([
            'timeout' => self::INDEX_CONNECTION_TIME_OUT,
            'allow_redirects' => false,
        ]);

        $this->output->progressStart($limit);
        foreach ($this->queueService->getQueue($limit) as $row) {
            try {
                $request = new CrawlerWebRequest(
                    $client,
                    $row['page_url']
                );

                if ($request->shouldIndex()) {
                    $this->elasticSearchService->addDocument($request->getElasticSearchIndex());
                    $indexed++;
                }

                // Dequeue
                $this->queueService->dequeue((int)$row['uid']);
            } catch (RequestNotFoundException $e) {
                // Dequeue
                $this->queueService->dequeue((int)$row['uid']);
            } catch (\Exception $e) {
                // This should be handled in code!
                $this->outputLine(date('c') . ':' . get_class($e) . ':' . $e->getCode() . ':' . $e->getMessage());
            }

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
     * @return boolean
     */
    public function indexUrlCommand($url = ''): bool
    {
        throw new NotImplementedException('@TODO: ' . __METHOD__, 1542805930588);
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
     * Find domain from core functionality based on root page ID (and store for local processing)
     *
     * @param int $rootPage
     * @return string
     */
    protected function getDomainForRootPageId(int $rootPage): ?string
    {
        if (!isset($this->domains[$rootPage])) {
            $protocol = GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https' : 'http';
            $domain = BackendUtility::firstDomainRecord(BackendUtility::BEgetRootLine($rootPage));
            $this->domains[$rootPage] = $protocol . '://' . $domain;
        }
        return $this->domains[$rootPage];
    }

    /**
     * @param string $tableName
     * @return Connection
     */
    public function getConnectionForTable(string $tableName): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
    }
}
