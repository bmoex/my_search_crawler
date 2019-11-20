<?php

namespace Serfhos\MySearchCrawler\Controller\Module;

use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Backend: Abstract ModuleActionController
 */
abstract class ModuleActionController extends ActionController
{
    /**
     * Override class variable for autocomplete
     *
     * @var \TYPO3\CMS\Backend\View\BackendTemplateView
     */
    protected $view;

    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /** @var \Serfhos\MySearchCrawler\Service\ElasticSearchService */
    protected $elasticSearchService;

    /**
     * Constructor: ModuleController: Statistics
     *
     * @param \Serfhos\MySearchCrawler\Service\ElasticSearchService $elasticSearchService
     */
    public function __construct(ElasticSearchService $elasticSearchService)
    {
        parent::__construct();
        $this->elasticSearchService = $elasticSearchService;
    }

    /**
     * Set up the doc header properly here
     *
     * @param \TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view
     */
    protected function initializeView(ViewInterface $view): void
    {
        /** @var \TYPO3\CMS\Backend\View\BackendTemplateView $view */
        parent::initializeView($view);
        if ($view instanceof BackendTemplateView) {
            $view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/Modal');

            $cluster = $this->elasticSearchService->info();
            if (isset($cluster['version']['number'])) {
                $view->assign('cluster', $cluster);
                $view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation([
                    'uid' => (string)$cluster['version']['number'],
                    'title' => 'ElasticSearch',
                ]);
            }
        }
    }
}
