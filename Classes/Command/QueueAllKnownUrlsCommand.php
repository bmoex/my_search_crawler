<?php

namespace Serfhos\MySearchCrawler\Command;

use Serfhos\MySearchCrawler\Command\Traits\EnsureEnvironment;
use Serfhos\MySearchCrawler\Service\QueueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Queue all available urls into the crawler index
 */
class QueueAllKnownUrlsCommand extends Command
{
    use EnsureEnvironment;

    /** @var \Serfhos\MySearchCrawler\Service\QueueService */
    protected $queueService;

    /** @var \TYPO3\CMS\Core\Database\QueryGenerator */
    protected $queryGenerator;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->ensureRequiredEnvironment();
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
        /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
        foreach (GeneralUtility::makeInstance(SiteFinder::class)->getAllSites() as $site) {
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
        ?array $excludedDocumentTypes = [3, 4, 6, 7, 199]
    ): QueryBuilder {
        $treeList = $this->getQueryGenerator()->getTreeList($rootPageId, 99, 0, true);

        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');

        $constraints = [
            $queryBuilder->expr()->in('uid', $treeList),
            $queryBuilder->expr()->lt('doktype', 200),
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
     * Wrapper for generating url via TypoLink functionality
     *
     * @param  int  $page
     * @return string|null
     */
    public function generateUrl(int $page): ?string
    {
        /** @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $cObj */
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $url = (string)$cObj->typoLink('', [
            'parameter' => 't3://page?uid=' . $page,
            'returnLast' => 'url',
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

    /**
     * @return \TYPO3\CMS\Core\Database\QueryGenerator
     */
    protected function getQueryGenerator(): QueryGenerator
    {
        if ($this->queryGenerator === null) {
            $this->queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        }

        return $this->queryGenerator;
    }
}
