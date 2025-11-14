<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\Api;

use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

readonly class Client
{
    public function __construct(
        private HttpDownloader $httpDownloader,
        private Loop $loop,
        private CacheInterface $cache,
    ) {}

    public function warmCache(string ...$slugs): void
    {
        $slugs = array_map('trim', $slugs);
        $slugs = array_filter($slugs, static fn (string $slug) => $slug !== '');

        $promises = array_map(
            fn (string $slug) => $this->isClosedAsync($slug),
            $slugs,
        );

        $this->loop->wait($promises);
    }

    public function isClosed(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $result = null;
        $promise = $this->isClosedAsync($slug)
            ->then(function (bool $isClosed) use (&$result): void {
                $result = $isClosed;
            });
        $this->loop->wait([$promise]);

        /** @var bool */
        return $result;
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function isClosedAsync(string $slug): PromiseInterface
    {
        /** @var Promise<bool> */
        return new Promise(function (callable $resolve) use ($slug): void {
            $cached = $this->cache->read($slug);
            $next = $cached ?? $this->fetchAndCacheAsync($slug);
            $resolve($next);
        });
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function fetchAndCacheAsync(string $slug): PromiseInterface
    {
        return $this->fetchAsync($slug)
            ->then(function (bool $isClosed) use ($slug): bool {
                $this->cache->write($slug, $isClosed);

                return $isClosed;
            });
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function fetchAsync(string $slug): PromiseInterface
    {
        $url = sprintf(
            'https://api.wordpress.org/plugins/info/1.2/?%s',
            http_build_query([
                'action' => 'plugin_information',
                'slug' => $slug,
            ], '', '&'),
        );

        /** 
         * Hack to disallow HTTP/3, forcing HttpDownloader to use RemoteFilesystem instead of CurlDownloader.
         *
         * I suspect api.wordpress.org does not properly support HTTP/3:
         *
         *     $ curl --http1.1 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=better-delete-revision'
         *     ...json response
         *
         *     $ curl --http2 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=better-delete-revision'
         *     ...json response
         *
         *     $ curl --http3-only 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=better-delete-revision'
         *     ...sometimes json response
         *     ...but most of the time ERR_DRAINING
         *     curl: (56) ngtcp2_conn_writev_stream returned error: ERR_DRAINING
         *
         * See:
         *   - https://github.com/composer/composer/pull/12363
         *   - https://github.com/composer/composer/blob/f5854b140ca27164d352ce30deece798acf3e36b/src/Composer/Util/HttpDownloader.php#L537
         */
        $options = [
            'ssl' => [
                'allow_self_signed' => true,
            ],
        ];

        return $this->httpDownloader->add($url, $options)
            ->then(static fn () => null) // Ignore successful responses. Closed plugins always return 404.
            ->catch(static function (TransportException $e): ?string {
                // Closed plugins always return 404.
                return $e->getStatusCode() === 404
                    ? $e->getResponse()
                    : null;
            })->then(static function (?string $body): bool {
                if ($body === null) {
                    return false;
                }

                /** @var array{error?: string} $json */
                $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $error = $json['error'] ?? null;

                return $error === 'closed';
            })->catch(static fn () => false);
    }
}
