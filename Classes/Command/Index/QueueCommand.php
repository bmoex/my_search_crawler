<?php

namespace Serfhos\MySearchCrawler\Command\Index;

use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Serfhos\MySearchCrawler\Command\Traits\EnsureEnvironment;
use Serfhos\MySearchCrawler\Service\CrawlerWebRequestService;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Service\QueueService;
use Serfhos\MySearchCrawler\Service\SimulatedUserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Crawl queued records
 */
class QueueCommand extends Command
{
    use EnsureEnvironment;

    /** @var \Serfhos\MySearchCrawler\Service\QueueService */
    protected $queueService;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->ensureRequiredEnvironment();
        $this->setDescription('Process queue');
        $this->addArgument('limit', InputArgument::OPTIONAL, 'Limit of queue', 50);
        $this->addArgument('frontendUserId', InputArgument::OPTIONAL, 'Frontend User to simulate', 0);
    }

    /**
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int|null
     */
    public function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $limit = $input->getArgument('limit') ?: 50;
        $frontendUserId = $input->getArgument('frontendUserId') ?: 0;

        $this->processQueue($output, $limit, $frontendUserId);

        return 0;
    }

    /**
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  int  $limit
     * @param  int  $frontendUserId
     * @return void
     */
    protected function processQueue(OutputInterface $output, int $limit, int $frontendUserId): void
    {
        $indexed = 0;
        $client = SimulatedUserService::createClientForFrontendUser($frontendUserId);
        $progressBar = new ProgressBar($output, $limit);
        foreach ($this->getQueueService()->getQueue($limit) as $row) {
            $url = $row['page_url'] ?? '';
            try {
                if ($url !== '' && $this->getCrawlerWebRequestService()->crawl($client, $url)) {
                    $indexed++;
                }

                // Always dequeue when handled, even if not handled
                $this->getQueueService()->dequeue((int)$row['uid']);
            } catch (ElasticsearchException $e) {
                // Make sure processed item is touched
                $this->getQueueService()->touch((int)$row['uid']);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        // Always add newline for site loop when progressbar is finished
        $output->writeln('');

        $output->writeln('<comment>Indexed: ' . $indexed . '</comment>');
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
     * @return \Serfhos\MySearchCrawler\Service\CrawlerWebRequestService
     */
    public function getCrawlerWebRequestService(): CrawlerWebRequestService
    {
        return GeneralUtility::makeInstance(CrawlerWebRequestService::class);
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\ElasticSearchService
     */
    public function getElasticSearchService(): ElasticSearchService
    {
        return GeneralUtility::makeInstance(ElasticSearchService::class);
    }
}
