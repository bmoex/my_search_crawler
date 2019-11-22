<?php

use Serfhos\MySearchCrawler\Command;

return [
    'my_search_crawler:queue_all_known_urls' => [
        'class' => Command\QueueAllKnownUrlsCommand::class,
        'schedulable' => false,
    ],
    'my_search_crawler:index:requeue' => [
        'class' => Command\Index\RequeueCommand::class,
    ],
    'my_search_crawler:index:queue' => [
        'class' => Command\Index\QueueCommand::class,
    ],
    'my_search_crawler:index:add' => [
        'class' => Command\Index\AddCommand::class,
        'schedulable' => false,
    ],
    'my_search_crawler:index:flush' => [
        'class' => Command\Index\FlushCommand::class,
        'schedulable' => false,
    ],
];
