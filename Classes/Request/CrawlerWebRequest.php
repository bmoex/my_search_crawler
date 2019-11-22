<?php

namespace Serfhos\MySearchCrawler\Request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\TransferStats;
use InvalidArgumentException;
use Serfhos\MySearchCrawler\Exception\RequestNotFoundException;
use Serfhos\MySearchCrawler\Exception\ShouldIndexException;
use Serfhos\MySearchCrawler\Utility\ConfigurationUtility;
use Symfony\Component\DomCrawler\Crawler;

/**
 * WebRequest: Crawler
 */
class CrawlerWebRequest
{

    /** @var \Psr\Http\Message\ResponseInterface */
    public $request;

    /** @var \Psr\Http\Message\UriInterface */
    protected $url;

    /** @var \Symfony\Component\DomCrawler\Crawler */
    protected $crawler;

    /**
     * Constructor: CrawlerWebRequest
     *
     * @param  \GuzzleHttp\Client  $client
     * @param  string  $uri
     */
    public function __construct(Client $client, string $uri)
    {
        try {
            $this->request = $client->get(
                ConfigurationUtility::crawlUrl($uri),
                [
                    'on_stats' => function (TransferStats $stats) use (&$url): void {
                        $url = $stats->getEffectiveUri();
                    },
                ]
            );
            $this->crawler = new Crawler(
                (string)$this->request->getBody(),
                $uri,
                (string)$client->getConfig('base_uri')
            );
            $this->url = $url;
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
     * @throws \Serfhos\MySearchCrawler\Exception\ShouldIndexException
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

        try {
            $crawledUrl = new Uri($this->crawler->getUri());
            $effectiveUrl = new Uri($this->url);

            if ($effectiveUrl->getAuthority() !== $crawledUrl->getAuthority()) {
                ShouldIndexException::throw(
                    'Link is not the same authority, seems this document should not be indexed.',
                    ['requested' => $crawledUrl, 'effective' => $effectiveUrl],
                    1547025139698
                );
            }

            // Check if link is different than canonical
            $canonicalUrl = $this->crawler->filter('link[rel=canonical]')->last()->attr('href');
            if ($canonicalUrl !== null) {
                $canonicalUrl = new Uri($canonicalUrl);

                if (Uri::isAbsolute($canonicalUrl)) {
                    if (!Uri::isSameDocumentReference($canonicalUrl, $crawledUrl)) {
                        ShouldIndexException::throw(
                            'Canonical link differs from requested url',
                            ['canonical' => $canonicalUrl, 'requested' => $crawledUrl],
                            1547025139698
                        );
                    }
                } else {
                    $relativeUrl = preg_replace('#^(://|[^/?])+#', '', (int)$effectiveUrl);
                    if ($canonicalUrl !== $crawledUrl) {
                        ShouldIndexException::throw(
                            'Canonical link differs from requested relative url',
                            [
                                'canonical' => $canonicalUrl,
                                'requested' => $crawledUrl,
                                'relative' => $relativeUrl,
                            ],
                            1574266503139
                        );
                    }
                }
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
     * @return string
     */
    public function getUri(): string
    {
        return $this->crawler->getUri();
    }

    /**
     * @return array
     */
    public function getMetaTags(): array
    {
        $metaTags = [];
        try {
            $this->crawler->filter('meta')->each(function (Crawler $node) use (&$metaTags): void {
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
    public function getTitle(): string
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
    public function getContent(): string
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
