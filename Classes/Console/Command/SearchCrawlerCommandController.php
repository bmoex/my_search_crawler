<?php
declare(strict_types=1);

namespace Serfhos\MySearchCrawler\Console\Command;

use GuzzleHttp\Client;
use Serfhos\MySearchCrawler\Exception\RequestNotFoundException;
use Serfhos\MySearchCrawler\Request\CrawlerWebRequest;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Service\QueueService;
use Serfhos\MySearchCrawler\Service\SimulatedUserService;
use Serfhos\MySearchCrawler\Service\UrlService;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
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
        $this->outputLine('');
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
            if ($request->shouldIndex()) {
                $this->elasticSearchService->addDocument($request->getElasticSearchIndex());
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
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)'
                . ' AppleWebKit/537.36 (KHTML, like Gecko)'
                . ' Chrome/48.0.2564.97'
                . ' Safari/537.36',
        ];
        if ($frontendUserId > 0) {
            $this->simulatedUserService = $this->simulatedUserService ?? new SimulatedUserService;
            if ($this->simulatedUserService->getSessionId($frontendUserId)) {
                $headers['Cookie'] = 'fe_typo_user=' . $this->simulatedUserService->getSessionId($frontendUserId);
            }
        }

        return new Client([
            'timeout' => self::INDEX_CONNECTION_TIME_OUT,
            'allow_redirects' => false,
            'headers' => $headers,
            'verify' => ConfigurationUtility::verify(),
        ]);
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
