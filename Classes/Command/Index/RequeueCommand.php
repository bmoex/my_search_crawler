<?php

namespace Serfhos\MySearchCrawler\Command\Index;

use DateTime;
use Exception;
use Serfhos\MySearchCrawler\Command\Traits\EnsureEnvironment;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Service\QueueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CommandController: SearchCrawler
 */
class RequeueCommand extends Command
{
    use EnsureEnvironment;

    /** @var \Serfhos\MySearchCrawler\Service\ElasticSearchService */
    protected $elasticSearchService;

    /** @var \Serfhos\MySearchCrawler\Service\QueueService */
    protected $queueService;

    /**
     * Configure the command by defining the name, options and arguments
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->ensureRequiredEnvironment();
        $this->setDescription('Add crawled url in current indexation');
        $this->addArgument('indexedSince', InputArgument::OPTIONAL);
    }

    /**
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int|void|null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasArgument('indexedSince') && $input->getArgument('indexedSince') !== null) {
            try {
                $indexedSince = (new DateTime('@' . strtotime($input->getArgument('indexedSince'))))
                    ->format(DateTime::ATOM);
            } catch (Exception $e) {
                $indexedSince = 'now-1d/d';
            }
        } else {
            $indexedSince = 'now-1d/d';
        }

        // Generator loop through all documents and requeue
        $this->requeueDocuments($output, $indexedSince);

        return 0;
    }

    /**
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string  $indexedSince
     * @return void
     */
    protected function requeueDocuments(OutputInterface $output, string $indexedSince): void
    {
        // Chunk query to avoid massive result lists
        $queued = 0;
        $scrollId = null;

        while (true) {
            try {
                $results = $this->getElasticSearchService()->scroll([
                    'sort' => ['indexed'],
                    'size' => 50,
                    'query' => ['range' => ['indexed' => ['lte' => $indexedSince]]],
                ], $scrollId);
            } catch (Exception $e) {
                break;
            }

            // Stop looping if no more results
            if (!isset($results['hits']['hits']) || empty($results['hits']['hits'])) {
                break;
            }

            foreach ($results['hits']['hits'] as $hit) {
                $url = $hit['_source']['url'] ?? '';
                if ($url === '') {
                    continue;
                }

                if ($this->getQueueService()->enqueue($url, ['through' => static::class])) {
                    $queued++;
                }
            }

            // Now define scroll id for further looping or break loop!
            if (isset($results['_scroll_id'])) {
                $scrollId = $results['_scroll_id'];
                continue;
            }
            break;
        }

        $output->writeln('Total queued records: ' . $queued);
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
     * @return \Serfhos\MySearchCrawler\Service\ElasticSearchService
     */
    protected function getElasticSearchService(): ElasticSearchService
    {
        if ($this->elasticSearchService === null) {
            $this->elasticSearchService = GeneralUtility::makeInstance(ElasticSearchService::class);
        }

        return $this->elasticSearchService;
    }
}
