<?php

namespace Serfhos\MySearchCrawler\Service;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service: Queue
 *
 * @package Serfhos\MySearchCrawler\Service
 */
class QueueService
{
    public const TABLE = 'tx_mysearchcrawler_domain_model_queue';

    /**
     * @var string
     */
    protected $queueId = '';

    /**
     * Constructor: Service: Queue
     */
    public function __construct()
    {
        $this->queueId = md5(uniqid('', true));
    }

    /**
     * Remove duplicated entries
     *
     * @return integer total of deleted items
     */
    public function cleanDuplicateQueueEntries(): int
    {
        try {
            return $this->getConnectionForTable(self::TABLE)->executeUpdate(
                'DELETE `a` FROM ' . self::TABLE . ' AS a, ' . self::TABLE . ' AS b '
                . ' WHERE a.uid < b.uid '
                . ' AND a.page_url <=> b.page_url;'
            );
        } catch (DBALException $e) {
            return 0;
        }
    }

    /**
     * @param array $row
     * @return boolean
     */
    public function enqueue(array $row): bool
    {
        return (bool)$this->getConnectionForTable(self::TABLE)->insert(self::TABLE, $row);
    }

    /**
     * @param integer $uid
     * @return boolean
     */
    public function dequeue(int $uid): bool
    {
        return (bool)$this->getConnectionForTable(self::TABLE)->delete(
            self::TABLE,
            ['uid' => $uid],
            [Connection::PARAM_INT]
        );
    }

    /**
     * Get queue generator for parallel executions
     *
     * @param integer $limit
     * @return \Generator
     */
    public function getQueue(int $limit): ?\Generator
    {
        $connection = $this->getConnectionForTable(self::TABLE);
        $exception = null;
        try {
            $connection->executeUpdate(
                'UPDATE ' . self::TABLE . ' '
                . ' SET running = "' . $this->queueId . '" '
                . ' LIMIT ' . $limit
            );
            $result = $connection->select(
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
            $connection->update(
                self::TABLE,
                ['running' => ''],
                ['running' => $this->queueId]
            );
        } catch (DBALException $e) {
            // Never throw exception, just log
            $this->getLogger()->error('1543405006140:' . $e->getMessage(), [$e]);
        }
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
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    public function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }
}
