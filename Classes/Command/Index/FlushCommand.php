<?php

namespace Serfhos\MySearchCrawler\Command\Index;

use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Flush full elastic search index via command line
 */
class FlushCommand extends Command
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Flush configured index');
    }

    /**
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int|null
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Flush full index
        $this->getElasticSearchService()->flush();
        $output->writeln('<info>Index is flushed!</info>');

        return 0;
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\ElasticSearchService
     */
    public function getElasticSearchService(): ElasticSearchService
    {
        return GeneralUtility::makeInstance(ElasticSearchService::class);
    }
}
