<?php

namespace Serfhos\MySearchCrawler\Request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Serfhos\MySearchCrawler\Domain\Model\Index\ElasticSearchIndex;
use Serfhos\MySearchCrawler\Exception\RequestNotFoundException;
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
            $this->request = $client->get($uri);
            $this->crawler = new Crawler(
                (string)$this->request->getBody(),
                $uri,
                (string)$client->getConfig('base_uri')
            );

            // Throw exception when 404 status
            if ($this->request->getStatusCode() === 404) {
                throw new RequestNotFoundException(
                    'Uri (' . $client->getConfig('base_uri') . $uri . ') responded with a Not Found status.',
                    1542785560414
                );
            }
        } catch (GuzzleException $e) {
            // Catch all possible guzzle exceptions..
            throw new RequestNotFoundException(
                $uri . '::' . $e->getMessage(),
                1542805489772
            );
        }
    }

    /**
     * @return bool
     */
    public function shouldIndex(): bool
    {
        if (!$this->request || !$this->crawler) {
            return false;
        }

        if ($this->request->getStatusCode() !== 200) {
            return false;
        }

        if ($this->request->hasHeader('X-Robots-Tag')) {
            $tags = $this->request->getHeader('X-Robots-Tag');
            foreach ($tags as $tag) {
                if (strpos($tag, 'noindex') !== false) {
                    return false;
                }
            }
        }

        try {
            $robots = $this->crawler->filter('meta[name=robots]')->first()->attr('content');
            if ($robots && strpos($robots, 'noindex') !== false) {
                return false;
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
            'url' => $this->getUrl(),
            'title' => $this->getTitle(),
            'meta' => $this->getMetaTags(),
            'content' => $this->getContent()
        ]);
        return $index;
    }

    /**
     * @return string
     */
    protected function getUrl(): string
    {
        $url = $this->crawler->getUri();
        try {
            $canonicalUrl = $this->crawler->filter('meta[property=canonical]')->first()->attr('content');
            if ($canonicalUrl) {
                $url = $canonicalUrl;
            }
        } catch (InvalidArgumentException $e) {
            // Never throw exception for lookup
        }

        return $url;
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
                                $content
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
        try {
            $elements = $this->crawler->filter('.content')->filterXPath('//text()[not(ancestor::script)]')->extract('_text');
            $content = implode('', $elements);
            $content = strip_tags($content);
            $content = preg_replace('/[\s]+/mu', ' ', $content);
        } catch (InvalidArgumentException $e) {
            // Never throw exception for lookup
        }

        return trim($content);
    }
}
