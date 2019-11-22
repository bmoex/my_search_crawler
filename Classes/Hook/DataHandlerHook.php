<?php

namespace Serfhos\MySearchCrawler\Hook;

use Serfhos\MySearchCrawler\Service\QueueService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Ignore sniff for custom hooks from TYPO3 Core
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

/**
 * Hook for processing database actions in backend
 *
 * @see \TYPO3\CMS\Core\DataHandling\DataHandler
 */
class DataHandlerHook
{
    /** @var array */
    protected $pageIds = [];

    /** @var \Serfhos\MySearchCrawler\Service\QueueService */
    protected $queueService;

    /**
     * @param  string  $status
     * @param  string  $table
     * @param  int  $id
     * @param  array  $fields
     * @param  \TYPO3\CMS\Core\DataHandling\DataHandler  $dataHandler
     * @return void
     * @see \TYPO3\CMS\Core\DataHandling\DataHandler::hook_processDatamap_afterDatabaseOperations
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int $id,
        array $fields,
        DataHandler $dataHandler
    ): void {
        if (!in_array($table, ['pages', 'tt_content'], true)) {
            return;
        }

        $pageId = ($table === 'pages') ? $id : $fields['pid'] ?? 0;
        if ($pageId === 0) {
            return;
        }

        $this->addPageIdToQueue($pageId);
    }

    /**
     * Requeue all pages from processDatamap_afterDatabaseOperations
     *
     * @param  \TYPO3\CMS\Core\DataHandling\DataHandler  $dataHandler
     * @return void
     * @see \TYPO3\CMS\Core\DataHandling\DataHandler::process_datamap
     */
    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $this->processPageIdsForQueue();
    }

    /**
     * @param  string  $command
     * @param  string  $table
     * @param  int  $id
     * @param  string|null  $value
     * @param  \TYPO3\CMS\Core\DataHandling\DataHandler  $dataHandler
     * @param  array|null  $pasteUpdate
     * @param  array|null  $pasteDatamap
     * @return void
     * @see \TYPO3\CMS\Core\DataHandling\DataHandler::process_cmdmap
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        int $id,
        ?string $value,
        DataHandler $dataHandler,
        ?array $pasteUpdate,
        ?array $pasteDatamap
    ): void {
        if (!in_array($table, ['pages', 'tt_content'], true)) {
            return;
        }

        $pageId = ($table === 'pages') ? $id : $fields['pid'] ?? 0;
        if ($pageId === 0) {
            return;
        }

        $this->addPageIdToQueue($pageId);
    }

    /**
     * Requeue all pages from processCmdmap_postProcess
     *
     * @param  \TYPO3\CMS\Core\DataHandling\DataHandler  $dataHandler
     * @return void
     * @see \TYPO3\CMS\Core\DataHandling\DataHandler::process_cmdmap
     */
    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $this->processPageIdsForQueue();
    }

    /**
     * @param  int  $pageId
     * @return void
     */
    protected function addPageIdToQueue(int $pageId): void
    {
        $this->pageIds[] = $pageId;

        // Make sure no duplicates are in
        $this->pageIds = array_unique($this->pageIds);
    }

    /**
     * @return void
     */
    protected function processPageIdsForQueue(): void
    {
        if (empty($this->pageIds)) {
            return;
        }

        foreach ($this->pageIds as $pageId) {
            $url = BackendUtility::getPreviewUrl($pageId);
            if ($url === null) {
                return;
            }

            $this->getQueueService()->enqueue($url, ['through' => 'DataHandlerHook']);
        }

        // Always clear page ids for possible duplicated usage
        $this->pageIds = [];
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\QueueService
     */
    protected function getQueueService(): QueueService
    {
        if ($this->queueService === null) {
            $this->queueService = GeneralUtility::makeInstance(QueueService::class);
        }

        return $this->queueService;
    }
}
