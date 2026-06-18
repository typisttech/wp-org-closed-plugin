<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpPackages\Api;

use Composer\Cache;
use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;

class Client
{
    /**
     * The wp-packages.org endpoint that returns every closed wp-plugin slug as
     * a single JSON array, e.g. ["better-delete-revision", "spam-stopgap"].
     */
    public const string URL = 'https://wp-packages.org/api/packages/wp-plugin/closed';

    private const string CACHE_KEY = 'closed.json';

    private const int CACHE_TTL_SECONDS = 600;

    /** @var array<string, true>|null */
    private ?array $closedSlugs = null;

    public function __construct(
        private readonly HttpDownloader $httpDownloader,
        private readonly Cache $cache,
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
        $cached = $this->cache->read(self::CACHE_KEY);
        $cachedSlugs = $this->decode($cached);
        if ($cachedSlugs !== null && $this->isCacheFresh()) {
            return $cachedSlugs;
        }

        $fallback = $cachedSlugs ?? [];

        try {
            $response = $this->httpDownloader->get(self::URL);
        } catch (TransportException) {
            return $fallback;
        }

        $body = $response->getBody();
        if ($body === null) {
            return $fallback;
        }

        $fresh = $this->decode($body);
        if ($fresh === null) {
            return $fallback;
        }

        $this->cache->write(self::CACHE_KEY, $body);

        return $fresh;
    }

    private function isCacheFresh(): bool
    {
        $age = $this->cache->getAge(self::CACHE_KEY);

        return $age !== false && $age < self::CACHE_TTL_SECONDS;
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
