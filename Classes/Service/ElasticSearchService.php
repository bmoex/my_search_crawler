<?php

namespace Serfhos\MySearchCrawler\Service;

use Elasticsearch\ClientBuilder;
use Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex;
use Serfhos\MySearchCrawler\Domain\Model\Index\Page;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;

/**
 * Service: ElasticSearch
 *
 * @package Serfhos\MySearchCrawler\Service
 */
class ElasticSearchService
{
    public const INDEX_TYPE = 'crawled_content';

    /**
     * @var string
     */
    protected $index;

    /**
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * ElasticSearchService constructor.
     */
    public function __construct()
    {
        $this->index = ConfigurationUtility::index();
        $this->client = ClientBuilder::create()
            ->setHosts(ConfigurationUtility::hosts())
            ->build();
    }

    /**
     * @return array
     */
    public function info(): array
    {
        return $this->client->info();
    }

    /**
     * @return array
     */
    public function indices(): ?array
    {
        $output = null;
        $indices = $this->client->indices()->stats();
        if (isset($indices['indices'])) {
            $output = [];
            foreach ($indices['indices'] as $name => $index) {
                $output[] = [
                        'name' => $name,
                        'access' => strpos($name, $this->index) === 0,
                    ] + $index;
            }
        }
        return $output;
    }

    /**
     * @param array $body
     * @return array
     */
    public function search($body = []): array
    {
        $parameters = [
            'index' => $this->index,
            'type' => self::INDEX_TYPE,
            'body' => $body,
        ];
        return $this->client->search($parameters);
    }

    /**
     * @param string $index
     * @return array
     */
    public function flush($index = null): array
    {
        $index = $index ?? $this->index;
        return $this->client->indices()->delete([
            'index' => $index,
        ]);
    }

    /**
     * @param ElasticSearchIndex $index
     * @return array
     */
    public function addDocument(ElasticSearchIndex $index): array
    {
        /** @var Page $index */
        if (!$this->client->indices()->exists(['index' => $this->index])) {
            $this->client->indices()->create([
                'index' => $this->index,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                    ],
                    'mappings' => [
                        self::INDEX_TYPE => [
                            'properties' => $index->getIndexProperties(),
                        ],
                    ],
                ],
            ]);
        }
        return $this->client->index([
            'index' => $this->index,
            'type' => self::INDEX_TYPE,
            'id' => $index->getIndexIdentifier(),
            'body' => $index->getDocumentBody(),
        ]);
    }

    /**
     * @param string $identifier
     * @return array
     */
    public function removeDocument($identifier): array
    {
        return $this->client->delete([
            'index' => $this->index,
            'type' => self::INDEX_TYPE,
            'id' => $identifier,
        ]);
    }
}
