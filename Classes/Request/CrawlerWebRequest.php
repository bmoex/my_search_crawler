<?php

namespace Serfhos\MySearchCrawler\Request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex;
use Serfhos\MySearchCrawler\Exception\RequestNotFoundException;
use Serfhos\MySearchCrawler\Exception\ShouldIndexException;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use Symfony\Component\DomCrawler\Crawler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * WebRequest: Crawler
 *
 * @package Serfhos\MySearchCrawler\Request
 */
class CrawlerWebRequest
{

    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    public $request;

    /**
     * @var Crawler
     */
    protected $crawler;

    /**
     * Constructor: CrawlerWebRequest
     *
     * @param Client $client
     * @param string $uri
     */
    public function __construct(Client $client, string $uri)
    {
        try {
            $this->request = $client->get(
                ConfigurationUtility::crawlUrl($uri)
            );
            $this->crawler = new Crawler(
                (string)$this->request->getBody(),
                $uri,
                (string)$client->getConfig('base_uri')
            );
        } catch (GuzzleException $e) {
            // Catch all possible guzzle exceptions..
            throw new RequestNotFoundException(
                $uri . '::' . $e->getMessage(),
                1542805489772
            );
        }
    }

    /**
     * @throws \Serfhos\MySearchCrawler\Exception\ShouldIndexException
     * @return bool
     */
    public function shouldIndex(): bool
    {
        if (!$this->request || !$this->crawler) {
            ShouldIndexException::throw(
                'No request or crawler defined',
                ['request' => $this->request, 'crawler' => $this->crawler],
                1547025004140
            );
        }

        if ($this->request->getStatusCode() !== 200) {
            ShouldIndexException::throw(
                'No `Found` (200) status response retrieved',
                ['statusCode' => $this->request->getStatusCode()],
                1547025092838
            );
        }

        // Check if link is different than canonical
        try {
            $canonicalUrl = $this->crawler->filter('link[rel=canonical]')->last()->attr('href');
            if ($canonicalUrl !== $this->crawler->getUri()) {
                ShouldIndexException::throw(
                    'Canonical link differs from requested url',
                    ['canonical' => $canonicalUrl, 'requested' => $this->crawler->getUri()],
                    1547025139698
                );
            }
        } catch (InvalidArgumentException $e) {
            // Never throw exception for lookup
        }

        // Check if robots no index is configured
        if ($this->request->hasHeader('X-Robots-Tag')) {
            $tags = $this->request->getHeader('X-Robots-Tag');
            foreach ($tags as $tag) {
                if (strpos($tag, 'noindex') !== false) {
                    ShouldIndexException::throw(
                        'X-Robots-Tag header retrieved with no index',
                        ['robots' => $tags],
                        1547025168687
                    );
                }
            }
        }

        try {
            $robots = $this->crawler->filter('meta[name=robots]')->last()->attr('content');
            if ($robots && strpos($robots, 'noindex') !== false) {
                ShouldIndexException::throw(
                    '<meta> robots tag configured with no index',
                    ['robots' => $robots],
                    1547025221601
                );
            }
        } catch (InvalidArgumentException $e) {
            // Never throw exception for lookup
        }

        return true;
    }

    /**
     * @return ElasticSearchIndex
     */
    public function getElasticSearchIndex(): ElasticSearchIndex
    {
        /** @var ElasticSearchIndex $index */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $index = $objectManager->get(ElasticSearchIndex::class, [
            'url' => $this->crawler->getUri(),
            'title' => $this->getTitle(),
            'meta' => $this->getMetaTags(),
            'content' => $this->getContent(),
        ]);
        return $index;
    }

    /**
     * @return array
     */
    protected function getMetaTags(): array
    {
        $metaTags = [];
        try {
            $this->crawler->filter('meta')->each(function (Crawler $node) use (&$metaTags) {
                $name = $content = null;
                if ($node->attr('name') && $node->attr('name') !== 'viewport') {
                    $name = $node->attr('name');
                    $content = $node->attr('content');
                }
                if ($node->attr('property')) {
                    $name = $node->attr('property');
                    $content = $node->attr('content');
                }

                if ($name && $content) {
                    if (isset($metaTags[$name])) {
                        if (is_array($metaTags[$name])) {
                            $metaTags[$name][] = $content;
                        } else {
                            $metaTags[$name] = [
                                $metaTags[$name],
                                $content,
                            ];
                        }
                    } else {
                        $metaTags[$name] = $content;
                    }
                }
            });
        } catch (InvalidArgumentException $e) {
            // Never throw exception for lookup
        }
        return $metaTags;
    }

    /**
     * @return string
     */
    protected function getTitle(): string
    {
        $title = '';
        try {
            $title = $this->crawler->filter('title')->html();
        } catch (InvalidArgumentException $e) {
            // Never throw exception for lookup
        }

        return $title;
    }

    /**
     * @return string
     */
    protected function getContent(): string
    {
        $content = '';
        $elements = [];
        try {
            $elements = $this->crawler->filter('.my-search-crawler-content')
                ->filterXPath('//text()[not(ancestor::script)]')
                ->extract('_text');
        } catch (InvalidArgumentException $e) {
        }

        if (empty($elements)) {
            try {
                $elements = $this->crawler->filter('.content')
                    ->filterXPath('//text()[not(ancestor::script)]')
                    ->extract('_text');
            } catch (InvalidArgumentException $e) {
                // Never throw exception for lookup
            }
        }

        if (!empty($elements)) {
            $content = implode(' ', $elements);
            $content = str_replace('<', ' <', $content);
            $content = strip_tags($content);
            $content = preg_replace('/[\s]+/mu', ' ', $content);
            $content = trim($content);
        }

        return $content;
    }
}
