<?php

namespace Serfhos\MySearchCrawler\Domain\Model\Index;

/**
 * ElasticSearch: Model Index
 */
interface ElasticSearchIndex
{
    /**
     * @return array
     */
    public function getIndexProperties(): array;

    /**
     * @return string
     */
    public function getIndexIdentifier(): string;

    /**
     * @return array
     */
    public function getDocumentBody(): array;
}
