<?php

namespace Serfhos\MySearchCrawler\Controller\Module;

use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Controller: Statistics
 *
 * @package Serfhos\MySearchCrawler\Controller\Module
 */
class StatisticsController extends ModuleActionController
{
    /**
     * Action: Statistics index
     */
    public function overviewAction(): void
    {
        $this->view->assign('indices', $this->elasticSearchService->indices());
    }

    /**
     * Action: Flush specific index
     * @param string $index
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function flushIndexAction($index = ''): void
    {
        if (empty($index)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('actions.flush.no_index_given', ConfigurationUtility::EXTENSION),
                '',
                AbstractMessage::ERROR
            );
        } elseif ($this->elasticSearchService->flush((string)$index)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('actions.flush.index_flushed', ConfigurationUtility::EXTENSION, [$index]),
                '',
                AbstractMessage::OK
            );
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate('actions.flush.no_index_flushed', ConfigurationUtility::EXTENSION, [$index]),
                '',
                AbstractMessage::WARNING
            );
        }
        $this->redirect('overview');
    }
}
