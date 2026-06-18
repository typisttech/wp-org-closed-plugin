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
     * Revalidate the cached list against the endpoint and return the closed set.
     *
     * A `304 Not Modified`, a transport failure, or an empty/malformed body all
     * fall back to the cached copy instead of discarding known data.
     *
     * @return array<string, true>
     */
    private function load(): array
    {
        $cached = $this->cache->read(self::CACHE_KEY);
        $fallback = $this->decode($cached) ?? [];

        try {
            $response = $this->httpDownloader->get(self::URL, $this->options());
        } catch (TransportException) {
            return $fallback;
        }

        $body = $response->getStatusCode() === 304 ? null : $response->getBody();
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

    /**
     * A conditional request whenever something is cached, so an unchanged list
     * returns a cheap `304 Not Modified` (Composer even synthesises one under
     * `COMPOSER_DISABLE_NETWORK`). The marker is the cache entry's own age.
     *
     * @return array{http?: array{header: list<string>}}
     */
    private function options(): array
    {
        $age = $this->cache->getAge(self::CACHE_KEY);
        if ($age === false) {
            return [];
        }

        $since = gmdate('D, d M Y H:i:s', time() - $age) . ' GMT';

        return ['http' => ['header' => ['If-Modified-Since: ' . $since]]];
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
