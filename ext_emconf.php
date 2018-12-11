<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Crawler: Elastic Indexing web crawler',
    'description' => 'Temporary elastic.co website crawler',
    'category' => 'plugin',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-8.7.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
