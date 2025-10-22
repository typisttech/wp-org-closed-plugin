<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\Api;

use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use React\Promise\PromiseInterface;

class Client
{
    public function __construct(
        private readonly HttpDownloader $httpDownloader,
        private readonly Loop $loop,
        private readonly Cache $cache,
    ) {}

    public function isClosed(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $cached = $this->cache->read($slug);
        if ($cached !== null) {
            return $cached;
        }

        $result = null;
        $promise = $this->fetchIsClosedAsync($slug)
            ->then(function (bool $isClosed) use (&$result, $slug): void {
                $this->cache->write($slug, $isClosed);
                $result = $isClosed;
            });

        $this->loop->wait([$promise]);

        /** @var bool */
        return $result;
    }

    /**
     * @return PromiseInterface<bool>
     */
    private function fetchIsClosedAsync(string $slug): PromiseInterface
    {
        $url = sprintf(
            'https://api.wordpress.org/plugins/info/1.2/?%s',
            http_build_query([
                'action' => 'plugin_information',
                'slug' => $slug,
            ], '', '&'),
        );

        return $this->httpDownloader->add($url)
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
