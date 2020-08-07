<?php

namespace Serfhos\MySearchCrawler\Command\Index;

use Exception;
use Serfhos\MySearchCrawler\Service\CrawlerWebRequestService;
use Serfhos\MySearchCrawler\Service\SimulatedUserService;
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
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Add crawled url in current indexation');
        $this->addArgument('url', InputArgument::REQUIRED);
        $this->addArgument('frontendUserId', InputArgument::OPTIONAL, 'Frontend User to simulate', 0);
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
        $simulatedUserService = $this->getSimulatedUserService();

        $client = $simulatedUserService->createClientForFrontendUser($frontendUserId);

        try {
            if ($this->getCrawlerWebRequestService()->crawl($client, $url, true)) {
                $output->writeln(
                    '<info>'
                    . 'Index successfully added (' . $url . ') to index (' . ConfigurationUtility::index() . ')'
                    . '</info>'
                );
            }
        } catch (Exception $e) {
            // This should be handled in code!
            $output->writeln(
                '<error>'
                . date('c') . ':' . get_class($e) . ':' . $e->getCode() . ':' . $e->getMessage()
                . '</error>'
            );
        }

        return 0;
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\CrawlerWebRequestService
     */
    public function getCrawlerWebRequestService(): CrawlerWebRequestService
    {
        return GeneralUtility::makeInstance(CrawlerWebRequestService::class);
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\SimulatedUserService
     */
    public function getSimulatedUserService(): SimulatedUserService
    {
        return GeneralUtility::makeInstance(SimulatedUserService::class);
    }
}
