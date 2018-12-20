<?php

namespace Serfhos\MySearchCrawler\Domain\Model\Index;

use DateTime;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Generic Index: Page object
 *
 * @package Serfhos\MySearchCrawler\Domain\Model\Index
 */
class Page implements ElasticSearchIndex
{
    /**
     * Constructor: Page
     */
    public function __construct($row)
    {
        $this->indexed = new DateTime();

        foreach ($row as $column => $value) {
            $methodName = 'set' . GeneralUtility::underscoredToUpperCamelCase($column);
            if (method_exists($this, $methodName)) {
                $this->{$methodName}($value);
            }
        }
    }

    /**
     * @var DateTime
     */
    protected $indexed;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var array
     */
    protected $meta = [];

    /**
     * @var string
     */
    protected $content = '';

    /**
     * @return DateTime
     */
    public function getIndexed(): DateTime
    {
        return $this->indexed;
    }

    /**
     * @param DateTime $indexed
     * @return $this
     */
    public function setIndexed(DateTime $indexed): self
    {
        $this->indexed = $indexed;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    protected function getIndexedTitle(): string
    {
        $title = $this->getTitle();
        // Override by og:title
        if ($this->getMeta()) {
            $title = $this->getLastMetaValue('og:title') ?? $title;
        }
        return (string)$title;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        $type = 'page';
        if ($this->getMeta()) {
            $type = $this->getLastMetaValue('type') ?? $type;
        }
        return (string)$type;
    }

    /**
     * @return array
     */
    protected function getSuggestions(): array
    {
        if ($this->getMeta() && $keywords = $this->getLastMetaValue('keywords')) {
            return GeneralUtility::trimExplode(',', $keywords, true);
        }

        return [];
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getLastMetaValue($key)
    {
        $values = $this->meta[$key] ?? [];
        if (empty($values)) {
            return null;
        }

        if (is_array($values)) {
            return end($values);
        }

        if (is_string($values)) {
            return $values;
        }
        return null;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param array $meta
     * @return $this
     */
    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return $this
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return array
     */
    public function getIndexProperties(): array
    {
        return [
            'suggest' => [
                'type' => 'completion',
            ],
            'indexed' => [
                'type' => 'date',
                'format' => 'strict_date_optional_time||epoch_millis',
            ],
            'url' => [
                'type' => 'text',
            ],
            'title' => [
                'type' => 'text',
            ],
            'type' => [
                'type' => 'text',
            ],
            'meta' => [
                // Multiple values, so it should not be defined as specific type
            ],
            'content' => [
                'type' => 'text',
            ],
        ];
    }

    /**
     * @return string
     */
    public function getIndexIdentifier(): string
    {
        return md5($this->getUrl());
    }

    /**
     * @return array
     */
    public function getDocumentBody(): array
    {
        return [
            'suggest' => $this->getSuggestions(),
            'indexed' => $this->getIndexed()->format(DateTime::ATOM),
            'url' => $this->getUrl(),
            'title' => $this->getIndexedTitle(),
            'meta' => $this->getMeta(),
            'type' => $this->getType(),
            'content' => $this->getContent(),
        ];
    }
}
