<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Crawler: Elastic Indexing web crawler',
    'description' => 'Temporary elastic.co website crawler',
    'category' => 'plugin',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '0.4.1',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
