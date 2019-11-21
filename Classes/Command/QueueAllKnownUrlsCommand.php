<?php

namespace Serfhos\MySearchCrawler\Command;

use Serfhos\MySearchCrawler\Service\QueueService;
use Serfhos\MySearchCrawler\Service\UrlService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Queue all available urls into the crawler index
 */
class QueueAllKnownUrlsCommand extends Command
{
    /** @var \Serfhos\MySearchCrawler\Service\QueueService */
    protected $queueService;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Queue all known pages for each configured Site based on TYPO3 context');
    }

    /**
     * Queue all known pages for each configured Site based on TYPO3 context
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int|null
     */
    public function execute(InputInterface $input, OutputInterface $output): ?int
    {
        foreach (GeneralUtility::makeInstance(SiteFinder::class)->getAllSites() as $site) {
            $this->initializeTypoScriptFrontendEnvironment($site->getRootPageId());
            $query = $this->getPagesQuery($site->getRootPageId());
            $result = $query->execute();
            $output->writeln('<info>Site: ' . $site->getIdentifier() . '</info>');

            $progressBar = new ProgressBar($output, $result->rowCount());
            while ($row = $result->fetch()) {
                $url = $this->generateUrl($row['uid']);
                if ($url) {
                    $this->getQueueService()->enqueue($url, ['through' => static::class]);
                }
                $progressBar->advance();
            }
            $progressBar->finish();

            // Always add newline for site loop when progressbar is finished
            $output->writeln('');
        }

        $output->writeln('<comment>All sites processed</comment>');

        return 0;
    }

    /**
     * @param  int  $rootPageId
     * @param  string|null  $additionalWhere
     * @param  array|null  $excludedDocumentTypes
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    protected function getPagesQuery(
        int $rootPageId,
        ?string $additionalWhere = 'no_index = 0 AND canonical_link = ""',
        ?array $excludedDocumentTypes = [3, 4, 6, 7, 199, 254, 255]
    ): QueryBuilder {
        $cObj = $GLOBALS['TSFE']->cObj;
        $treeList = $cObj->getTreeList(-$rootPageId, 99, 0, true);
        $treeListArray = GeneralUtility::intExplode(',', $treeList);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');

        $constraints = [
            $queryBuilder->expr()->in('uid', $treeListArray),
        ];

        if ($additionalWhere !== null) {
            $constraints[] = QueryHelper::stripLogicalOperatorPrefix($additionalWhere);
        }

        if ($excludedDocumentTypes !== null && !empty($excludedDocumentTypes)) {
            $constraints[] = $queryBuilder->expr()->notIn('doktype', implode(',', $excludedDocumentTypes));
        }

        return $queryBuilder->select('*')
            ->from('pages')
            ->where(...$constraints)
            ->orderBy('uid', 'ASC');
    }

    /**
     * @param  int  $rootPageId
     * @return void
     */
    public function initializeTypoScriptFrontendEnvironment(int $rootPageId): void
    {
        // This usually happens when typolink is created by the TYPO3 Backend, where no TSFE object
        // is there. This functionality is currently completely internal, as these links cannot be
        // created properly from the Backend.
        // However, this is added to avoid any exceptions when trying to create a link
        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            null,
            $rootPageId,
            0
        );
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        $GLOBALS['TSFE']->tmpl = GeneralUtility::makeInstance(TemplateService::class);
        $GLOBALS['TSFE']->initFEuser();
        $GLOBALS['TSFE']->newCObj();
        $GLOBALS['TSFE']->domainStartPage = $rootPageId;
        $GLOBALS['TSFE']->fetch_the_id();
    }

    /**
     * Wrapper for generating url via TypoLink functionality
     *
     * @param  int  $page
     * @return string|null
     */
    public function generateUrl(int $page): ?string
    {
        $url = $GLOBALS['TSFE']->cObj->typoLink_URL([
            'parameter' => 't3://page?uid=' . $page,
            'forceAbsoluteUrl' => true,
        ]);
        if (!GeneralUtility::isValidUrl($url)) {
            return null;
        }

        return $url;
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
