<?php

namespace Serfhos\MySearchCrawler\Service;

use Doctrine\DBAL\DBALException;
use Generator;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service: Queue
 */
class QueueService
{
    public const TABLE = 'tx_mysearchcrawler_domain_model_queue';

    /** @var int */
    protected $runtime;

    /** @var string */
    protected $queueId;

    /** @var \TYPO3\CMS\Core\Database\Connection */
    protected $connection;

    /**
     * @param  \TYPO3\CMS\Core\Database\Connection|null  $connection
     */
    public function __construct(?Connection $connection = null)
    {
        $this->runtime = time();
        $this->queueId = md5(uniqid('', true));
        $this->connection = $connection
            ?? GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable(self::TABLE);
    }

    /**
     * @param  string  $url
     * @return string
     */
    public function generateIdentifier(string $url): string
    {
        return md5($url);
    }

    /**
     * @param  string  $identifier
     * @return bool
     */
    protected function isQueued(string $identifier): bool
    {
        return (bool)$this->connection->count('uid', self::TABLE, ['identifier' => $identifier]);
    }

    /**
     * @param  string  $url
     * @param  array|null  $caller
     * @return bool
     */
    public function enqueue(string $url, ?array $caller = null): bool
    {
        $identifier = $this->generateIdentifier($url);
        if ($this->isQueued($identifier)) {
            return false;
        }

        return (bool)$this->connection->insert(self::TABLE, [
            'crdate' => $this->runtime,
            'tstamp' => $this->runtime,
            'cruser_id' => $GLOBALS['BE_USER']->user['id'] ?? 0,
            'identifier' => $identifier,
            'page_url' => $url,
            'caller' => $caller ? json_encode($caller, JSON_PRETTY_PRINT) : null,
        ]);
    }

    /**
     * @param  int  $uid
     * @return bool
     */
    public function dequeue(int $uid): bool
    {
        return (bool)$this->connection->delete(self::TABLE, ['uid' => $uid], [Connection::PARAM_INT]);
    }

    /**
     * @param  int  $uid
     * @return bool
     */
    public function touch(int $uid): bool
    {
        return (bool)$this->connection->update(self::TABLE, ['tstamp' => $this->runtime], ['uid' => $uid]);
    }

    /**
     * Get queue generator for parallel executions
     *
     * @param  int  $limit
     * @return \Generator
     */
    public function getQueue(int $limit): ?Generator
    {
        try {
            $this->connection->executeUpdate(
                'UPDATE ' . self::TABLE . ' '
                . ' SET running = "' . $this->queueId . '" '
                . ' WHERE running = ""'
                . ' ORDER BY tstamp ASC LIMIT ' . $limit
            );
            $result = $this->connection->select(
                ['uid', 'identifier', 'page_url', 'caller'],
                self::TABLE,
                ['running' => $this->queueId]
            );
            while ($row = $result->fetch()) {
                yield $row;
            }
        } catch (DBALException $e) {
            // Never throw exceptions
        }

        try {
            // Always remove runner id from queue and still throw exception
            $this->connection->update(
                self::TABLE,
                ['running' => ''],
                ['running' => $this->queueId]
            );
        } catch (DBALException $e) {
            // Never throw exception, just log
            $this->getLogger()->error('1596803352:' . $e->getMessage(), [$e]);
        }
    }

    /**
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    public function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
    }
}
