<?php

namespace Serfhos\MySearchCrawler\Service;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Service: ElasticSearch
 */
class ElasticSearchService implements SingletonInterface
{
    public const INDEX_TYPE = 'crawled_content';

    /** @var string */
    protected $index;

    /** @var \Elasticsearch\Client */
    protected $client;

    public function __construct()
    {
        $this->setIndex(ConfigurationUtility::index());
        $this->setClient(ClientBuilder::create()
            ->setHosts(ConfigurationUtility::hosts())
            ->build());
    }

    /**
     * @param  string  $index
     * @return $this
     */
    public function setIndex(string $index): self
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return string
     */
    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @param  \Elasticsearch\Client  $client
     * @return $this
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return \Elasticsearch\Client
     */
    public function getClient(): Client
    {
        return $this->client;
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
     * @param  array  $body
     * @param  string  $index
     * @return array
     */
    public function count(array $body = [], ?string $index = null): array
    {
        $index = $index ?? $this->index;
        $parameters = [
            'index' => $index,
            'type' => self::INDEX_TYPE,
            'body' => $body,
        ];

        return $this->client->count($parameters);
    }

    /**
     * @param  array  $body
     * @param  string  $index
     * @return array
     */
    public function search(array $body = [], ?string $index = null): array
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
     * @param  array  $body
     * @param  string  $scrollId
     * @param  string|null  $index
     * @return array
     */
    public function scroll(array $body = [], ?string $scrollId = null, ?string $index = null): array
    {
        $index = $index ?? $this->index;

        if ($scrollId === null) {
            $parameters = [
                'scroll' => '30s',
                'index' => $index,
                'type' => self::INDEX_TYPE,
                'body' => $body,
            ];

            return $this->client->search($parameters);
        }

        $parameters = [
            'scroll' => '30s',
            'scroll_id' => $scrollId,
        ];

        return $this->client->scroll($parameters);
    }

    /**
     * @param  string  $index
     * @return array
     */
    public function flush(?string $index = null): array
    {
        $index = $index ?? $this->index;

        return $this->client->indices()->delete([
            'index' => $index,
        ]);
    }

    /**
     * @param  array  $body
     * @param  string  $index
     * @return array
     */
    public function deleteByQuery(array $body, ?string $index = null): array
    {
        $index = $index ?? $this->index;

        return $this->client->deleteByQuery([
            'index' => $index,
            'type' => self::INDEX_TYPE,
            'body' => $body,
        ]);
    }

    /**
     * @param  \Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex  $document
     * @param  string  $index
     * @return array
     */
    public function addDocument(ElasticSearchIndex $document, ?string $index = null): array
    {
        $index = $index ?? $this->index;
        /** @var \Serfhos\MySearchCrawler\Domain\Model\Index\Page $document */
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
     * @param  string  $identifier
     * @param  string  $index
     * @return array
     */
    public function removeDocument(string $identifier, ?string $index = null): array
    {
        return $this->client->delete([
            'index' => $index ?? $this->index,
            'type' => self::INDEX_TYPE,
            'id' => $identifier,
        ]);
    }
}
