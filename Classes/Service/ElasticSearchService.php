<?php

namespace Serfhos\MySearchCrawler\Service;

use Elasticsearch\Client;
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
        $this->setIndex(ConfigurationUtility::index());
        $this->setClient(ClientBuilder::create()
            ->setHosts(ConfigurationUtility::hosts())
            ->build());
    }

    /**
     * @param string $index
     * @return $this
     */
    public function setIndex(string $index): self
    {
        $this->index = $index;
        return $this;
    }

    /**
     * @param \Elasticsearch\Client $client
     * @return $this
     */
    public function setClient($client): self
    {
        $this->client = $client;
        return $this;
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
     * @param string $index
     * @return array
     */
    public function search($body = [], $index = null): array
    {
        $index = $index ?? $this->index;
        $parameters = [
            'index' => $index,
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
     * @param array $body
     * @param string $index
     * @return array
     */
    public function deleteByQuery(array $body, $index = null): array
    {
        $index = $index ?? $this->index;
        return $this->client->deleteByQuery([
            'index' => $index,
            'type' => self::INDEX_TYPE,
            'body' => $body,
        ]);
    }

    /**
     * @param ElasticSearchIndex $document
     * @param string $index
     * @return array
     */
    public function addDocument(ElasticSearchIndex $document, $index = null): array
    {
        $index = $index ?? $this->index;
        /** @var Page $document */
        if (!$this->client->indices()->exists(['index' => $index])) {
            $this->client->indices()->create([
                'index' => $this->index,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                    ],
                    'mappings' => [
                        self::INDEX_TYPE => [
                            'properties' => $document->getIndexProperties(),
                        ],
                    ],
                ],
            ]);
        }
        return $this->client->index([
            'index' => $this->index,
            'type' => self::INDEX_TYPE,
            'id' => $document->getIndexIdentifier(),
            'body' => $document->getDocumentBody(),
        ]);
    }

    /**
     * @param string $identifier
     * @param string $index
     * @return array
     */
    public function removeDocument($identifier, $index = null): array
    {
        return $this->client->delete([
            'index' => $index ?? $this->index,
            'type' => self::INDEX_TYPE,
            'id' => $identifier,
        ]);
    }
}
