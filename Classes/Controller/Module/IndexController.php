<?php
declare(strict_types=1);

namespace Serfhos\MySearchCrawler\Controller\Module;

use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Controller: Index
 * @package Serfhos\MySearchCrawler\Controller
 */
class IndexController extends ModuleActionController
{

    /**
     * @param string $index
     * @param string $body
     * @return void
     */
    public function findByQueryAction(string $index, string $body = null): void
    {
        $this->view->assignMultiple([
            'index' => $index,
            'body' => $body ?? json_encode([
                    'query' => [
                        'simple_query_string' => [
                            'query' => 'Search',
                            'fields' => ['content', 'title^5', 'url']
                        ],
                    ]
                ], JSON_PRETTY_PRINT)
        ]);

        if ($body) {
            try {
                $body = json_decode($body, true);
                $this->view->assign('results', $this->elasticSearchService->search($body, $index));
            } catch (ElasticsearchException $e) {
                $this->addFlashMessage(
                    LocalizationUtility::translate('actions.find_by_query.no_valid_body_given', ConfigurationUtility::EXTENSION, [$e->getMessage()]),
                    '',
                    AbstractMessage::ERROR
                );
            }
        }
    }

    /**
     * @param string $index
     * @param string $body
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function deleteByQueryAction(string $index, string $body): void
    {
        $_body = $body;
        $body = json_decode($body, true);
        if (empty($body)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('actions.delete_by_query.no_valid_query_given', ConfigurationUtility::EXTENSION),
                '',
                AbstractMessage::ERROR
            );
        } else {
            $result = $this->elasticSearchService->deleteByQuery($body, $index);
            if ($result) {
                $this->addFlashMessage(
                    LocalizationUtility::translate('actions.delete_by_query.successful', ConfigurationUtility::EXTENSION, [$body]),
                    '',
                    AbstractMessage::OK
                );
            } else {
                $this->addFlashMessage(
                    LocalizationUtility::translate('actions.delete_by_query.unsuccessful', ConfigurationUtility::EXTENSION, [$body]),
                    '',
                    AbstractMessage::WARNING
                );
            }
        }
        $this->redirect('findByQuery', 'Module\Index', null, ['index' => $index, 'body' => $_body]);
    }

    /**
     * Action: Flush specific document from index
     * @param string $index
     * @param string $document
     * @param string $body
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function deleteDocumentAction(string $index, string $document, string $body = null): void
    {
        if (empty($document)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('actions.delete_document.no_document_given', ConfigurationUtility::EXTENSION),
                '',
                AbstractMessage::ERROR
            );
        } elseif ($this->elasticSearchService->removeDocument($document, $index)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('actions.delete_document.successful', ConfigurationUtility::EXTENSION, [$document]),
                '',
                AbstractMessage::OK
            );
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate('actions.delete_document.unsuccessful', ConfigurationUtility::EXTENSION, [$document]),
                '',
                AbstractMessage::WARNING
            );
        }
        $this->redirect('findByQuery', 'Module\Index', null, ['index' => $index, 'body' => $body]);
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
        $this->redirect('overview', 'Module\Statistics');
    }
}
