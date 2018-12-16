<?php

namespace Serfhos\MySearchCrawler\Controller\Module;

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
}
