<?php

declare(strict_types=1);

namespace Serfhos\MySearchCrawler\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Serfhos\MySearchCrawler\Service\ElasticSearchService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Frontend AJAX Controller: SearchAPI
 */
class SearchAPIController
{
    /** @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface */
    protected $objectManager;

    /**
     * Constructor: SearchAPI
     *
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function __construct(?ObjectManagerInterface $objectManager = null)
    {
        $this->objectManager = $objectManager ?? GeneralUtility::makeInstance(ObjectManager::class);
    }

    /**
     * AJAX Action: Autocomplete
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function autocomplete(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        // Parameters
        $query = (string)$this->getParameter($request, 'query', '');

        $suggestions = [];
        if (strlen($query) > 2) {
            $result = $this->getElasticSearchService()->search([
                'suggest' => [
                    'try' => [
                        'prefix' => $query,
                        'completion' => [
                            'field' => 'suggest',
                        ],
                    ],
                ],
            ]);

            if (isset($result['suggest']['try'][0]['options'])) {
                foreach ($result['suggest']['try'][0]['options'] as $option) {
                    $suggestions[] = $option['text'];
                }
            }
        }

        $response->getBody()->write(json_encode($suggestions));
        return $response->withAddedHeader('Content-Type', 'application/json');
    }

    /**
     * AJAX Action: search
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function search(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        // Parameters
        $query = (string)$this->getParameter($request, 'query', '');
        $page = (int)$this->getParameter($request, 'page', 1);
        $size = (int)$this->getParameter($request, 'page_size', 10);

        $result = [];
        if (strlen($query) > 2) {
            $result = $this->getElasticSearchService()->search([
                'from' => $size * ($page - 1),
                'size' => $size,
                'query' => [
                    'match_phrase_prefix' => [
                        'content' => $query,
                    ],
                ],
                'highlight' => [
                    'fields' => [
                        'content' => [
                            'pre_tags' => '<em>',
                            'post_tags' => '</em>',
                        ],
                    ],
                ],
            ]);
        }

        $from = $size * ($page - 1);
        $to = $from + $size;

        $output = [
            'information' => [
                'total' => 0,
                'page' => 1,
                'from' => 0,
                'to' => 0,
            ],
            'data' => [],
        ];

        if (isset($result['hits'])) {
            $from = ($from <= $result['hits']['total']) ? $from : 0;
            $to = $to <= $result['hits']['total'] ? $to : $result['hits']['total'];
            $output['information'] = [
                'total' => $result['hits']['total'],
                'page' => $page,
                'from' => $from,
                'to' => $from !== 0 ? $to : 0,
            ];
            if (isset($result['hits']['hits'][0])) {
                foreach ($result['hits']['hits'] as $hit) {
                    $row = $hit['_source'];
                    $row['highlight'] = $hit['highlight']['content'];
                    $output['data'][] = $row;
                }
            }
        }

        $response->getBody()->write(json_encode($output));
        return $response->withAddedHeader('Content-Type', 'application/json');
    }

    /**
     * @return \Serfhos\MySearchCrawler\Service\ElasticSearchService
     */
    protected function getElasticSearchService(): ElasticSearchService
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        return $objectManager->get(ElasticSearchService::class);
    }

    /**
     * Get parameter from given request (POST => GET)
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param string $parameter
     * @param mixed $default
     * @return mixed
     */
    protected function getParameter(ServerRequestInterface $request, $parameter, $default = null)
    {
        return $request->getParsedBody()[$parameter] ?? $request->getQueryParams()[$parameter] ?? $default;
    }
}
