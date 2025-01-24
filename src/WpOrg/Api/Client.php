<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\Api;

use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

use function React\Promise\resolve;

readonly class Client
{
    public function __construct(
        private HttpDownloader $httpDownloader,
    ) {}

    /**
     * @return PromiseInterface<bool>
     */
    public function isClosedAsync(string $slug): PromiseInterface
    {
        $slug = trim($slug);
        if (empty($slug)) {
            return resolve(false);
        }

        $url = sprintf(
            'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=%s',
            $slug,
        );

        return $this->httpDownloader->add($url)
            ->then(static fn () => throw new RuntimeException)
            ->catch(static function (Throwable $e): string {
                if (! $e instanceof TransportException) {
                    throw $e;
                }

                // Closed plugins always return 404.
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }

                return $e->getResponse();
            })->then(function (string $body): true {
                $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $error = $json['error'] ?? null;

                return $error === 'closed';
            })->catch(static fn () => false);
    }
}
