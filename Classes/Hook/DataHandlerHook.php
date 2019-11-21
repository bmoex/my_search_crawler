<?php

namespace Serfhos\MySearchCrawler\Hook;

use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Hook for processing database actions in backend
 *  + CodeSniffer disabled for hooks
 *
 * @see \TYPO3\CMS\Core\DataHandling\DataHandler
 */
class DataHandlerHook
{
    //
    // phpcs:disable
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
    }

    /**
     * @param  \TYPO3\CMS\Core\DataHandling\DataHandler  $dataHandler
     * @return void
     */
    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
    }
    // phpcs:enable

}
