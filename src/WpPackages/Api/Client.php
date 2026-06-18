<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpPackages\Api;

use Composer\Cache;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\HttpDownloader;

class Client
{
    /**
     * The wp-packages.org endpoint that returns every closed wp-plugin slug as
     * a single JSON array, e.g. ["better-delete-revision", "spam-stopgap"].
     */
    public const string URL = 'https://wp-packages.org/api/packages/wp-plugin/closed';

    private const int CACHE_TTL_SECONDS = 600;

    /** @var array<string, true>|null */
    private ?array $closedSlugs = null;

    public function __construct(
        private readonly HttpDownloader $httpDownloader,
        private readonly Cache $cache,
        private readonly ?EventDispatcher $eventDispatcher = null,
    ) {}

    public function isClosed(string $slug): bool
    {
        $slug = trim($slug);

        return $slug !== '' && isset($this->closedSlugs()[$slug]);
    }

    /**
     * The closed slugs as a set keyed by slug, resolved at most once per run.
     *
     * @return array<string, true>
     */
    private function closedSlugs(): array
    {
        return $this->closedSlugs ??= $this->load();
    }

    /**
     * Return the cached list while it is fresh, otherwise refresh from the endpoint.
     *
     * A transport failure or an empty/malformed body falls back to the cached
     * copy instead of discarding known data.
     *
     * @return array<string, true>
     */
    private function load(): array
    {
        $download = $this->download();
        $cached = $this->cache->read($download['cacheKey']);
        $cachedSlugs = $this->decode($cached);
        if ($cachedSlugs !== null && $this->isCacheFresh($download['cacheKey'])) {
            return $cachedSlugs;
        }

        $fallback = $cachedSlugs ?? [];

        try {
            $response = $this->httpDownloader->get($download['url'], $download['options']);
        } catch (TransportException) {
            return $fallback;
        }

        if ($this->eventDispatcher !== null) {
            $postFileDownloadEvent = new PostFileDownloadEvent(
                PluginEvents::POST_FILE_DOWNLOAD,
                null,
                null,
                $download['url'],
                'metadata',
                ['response' => $response, 'wp-packages-api-client' => $this],
            );
            $this->eventDispatcher->dispatch($postFileDownloadEvent->getName(), $postFileDownloadEvent);
        }

        $body = $response->getBody();
        if ($body === null) {
            return $fallback;
        }

        $fresh = $this->decode($body);
        if ($fresh === null) {
            return $fallback;
        }

        $this->cache->write($download['cacheKey'], $body);

        return $fresh;
    }

    private function isCacheFresh(string $cacheKey): bool
    {
        $age = $this->cache->getAge($cacheKey);

        return $age !== false && $age < self::CACHE_TTL_SECONDS;
    }

    /**
     * @return array{url: non-empty-string, options: mixed[], cacheKey: string}
     */
    private function download(): array
    {
        $url = self::URL;
        $options = [];
        $cacheKey = $url;

        if ($this->eventDispatcher === null) {
            return ['url' => $url, 'options' => $options, 'cacheKey' => $cacheKey];
        }

        $preFileDownloadEvent = new PreFileDownloadEvent(
            PluginEvents::PRE_FILE_DOWNLOAD,
            $this->httpDownloader,
            $url,
            'metadata',
            ['wp-packages-api-client' => $this],
        );
        $preFileDownloadEvent->setTransportOptions($options);
        $this->eventDispatcher->dispatch($preFileDownloadEvent->getName(), $preFileDownloadEvent);

        $url = $preFileDownloadEvent->getProcessedUrl();
        $options = $preFileDownloadEvent->getTransportOptions();
        $cacheKey = $preFileDownloadEvent->getCustomCacheKey() ?? $url;

        return ['url' => $url, 'options' => $options, 'cacheKey' => $cacheKey];
    }

    /**
     * Decode the endpoint's JSON array of slugs into a set keyed by slug, or
     * null when the payload is not a JSON array so the caller can fall back.
     *
     * @return array<string, true>|null
     */
    private function decode(string|false $body): ?array
    {
        $slugs = $body === false ? null : json_decode($body, true);
        if (! is_array($slugs) || ! array_is_list($slugs)) {
            return null;
        }

        return array_fill_keys(array_filter($slugs, is_string(...)), true);
    }
}
