<?php

declare(strict_types=1);

namespace Serfhos\MySearchCrawler\Command\Index;

use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Serfhos\MySearchCrawler\Exception\ShouldIndexException;
use Serfhos\MySearchCrawler\Request\CrawlerWebRequest;
use Serfhos\MySearchCrawler\Service\ClientService;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Cli Command: Add crawled url in current indexation
 */
class AddCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Add crawled url in current indexation');
        $this->addArgument('url', InputArgument::REQUIRED);
        $this->addArgument('frontendUserId', InputArgument::OPTIONAL);
    }

    /**
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int|null null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $url = $input->getArgument('url');
        $frontendUserId = $input->getArgument('frontendUserId') ?: 0;
        $client = $this->getClientService()->createForFrontendUser($frontendUserId);

        try {
            $request = new CrawlerWebRequest(
                $client,
                $url
            );

            if ($this->index($request, true)) {
                $output->writeln('Index successfully added (' . $url . ') to index (' . ConfigurationUtility::index() . ')');
            }
        } catch (\Exception $e) {
            // This should be handled in code!
            $output->writeln(date('c') . ':' . get_class($e) . ':' . $e->getCode() . ':' . $e->getMessage());
        }

        return 0;
    }

    /**
     * @param  \Serfhos\MySearchCrawler\Request\CrawlerWebRequest  $request
     * @param  bool  $throw
     * @return boolean
     * @throws \Serfhos\MySearchCrawler\Exception\ShouldIndexException if $throw is configured
     */
    protected function index(CrawlerWebRequest $request, $throw = false): bool
    {
        $index = $request->getElasticSearchIndex();
        try {
            if ($request->shouldIndex()) {
                $this->getElasticSearchService()->addDocument($index);
            }

            return true;
        } catch (ShouldIndexException $e) {
            // Always try to delete document!
            try {
                $this->getElasticSearchService()->removeDocument($index->getIndexIdentifier());
            } catch (ElasticsearchException $_e) {
                // Do nothing..
            }

            if ($throw) {
                throw $e;
            }
        }

        return false;
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\ClientService
     */
    public function getClientService(): ClientService
    {
        return GeneralUtility::makeInstance(ClientService::class);
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\ElasticSearchService
     */
    public function getElasticSearchService(): ElasticSearchService
    {
        return GeneralUtility::makeInstance(ElasticSearchService::class);
    }
}
