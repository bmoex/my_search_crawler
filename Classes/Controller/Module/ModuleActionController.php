<?php

namespace Serfhos\MySearchCrawler\Controller\Module;

use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Backend: Abstract ModuleActionController
 *
 * @package Serfhos\MySearchCrawler\Controller\Module
 */
abstract class ModuleActionController extends ActionController
{
    /**
     * Override class variable for autocomplete
     *
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * @var ElasticSearchService
     */
    protected $elasticSearchService;

    /**
     * Constructor: ModuleController: Statistics
     *
     * @param ElasticSearchService $elasticSearchService
     */
    public function __construct(ElasticSearchService $elasticSearchService)
    {
        parent::__construct();
        $this->elasticSearchService = $elasticSearchService;
    }

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     */
    protected function initializeView(ViewInterface $view): void
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($view instanceof BackendTemplateView) {
            $view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/Modal');

            $cluster = $this->elasticSearchService->info();
            if (isset($cluster['version']['number'])) {
                $view->assign('cluster', $cluster);
                $view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation([
                    'uid' => (string)$cluster['version']['number'],
                    'title' => 'ElasticSearch'
                ]);
            }
        }
    }
}
