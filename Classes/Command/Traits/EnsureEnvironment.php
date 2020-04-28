<?php

namespace Serfhos\MySearchCrawler\Command\Traits;

trait EnsureEnvironment
{
    /**
     * Checks PHP sapi type and sets required PHP options
     *
     * @return void
     */
    private function ensureRequiredEnvironment(): void
    {
        // The command line must be executed with a cli PHP binary! It could be manually invoked through frontend.
        if (!isset($_SERVER['argc'], $_SERVER['argv']) || !in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            return;
        }

        if (ini_get('memory_limit') !== '-1') {
            @ini_set('memory_limit', '-1');
        }
        if (ini_get('max_execution_time') !== '0') {
            @ini_set('max_execution_time', '0');
        }
    }
}
