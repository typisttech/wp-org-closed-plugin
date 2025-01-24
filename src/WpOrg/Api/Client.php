<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\Api;

use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

use function React\Promise\reject;

readonly class Client
{
    private const string DEFAULT_CLOSED_DESCRIPTION = 'This plugin has been closed on wordpress.org. Reason: Unknown.';

    public function __construct(
        private HttpDownloader $httpDownloader,
    ) {}

    /**
     * @return PromiseInterface<string|null>
     */
    public function asyncFetchClosedDescription(string $slug): PromiseInterface
    {
        $slug = trim($slug);
        if (empty($slug)) {
            return reject(new UnexpectedValueException('Argument $slug may not be empty.'));
        }

        $url = sprintf(
            'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=%s',
            $slug,
        );

        return $this->httpDownloader->add($url)
            ->then(static function () use ($slug): never {
                // Closed plugins always return 404.
                throw new RuntimeException(
                    sprintf('%s is not a closed plugin.', $slug)
                );
            })->catch(static function (Throwable $e): string {
                if (! $e instanceof TransportException) {
                    throw $e;
                }

                // Closed plugins always return 404.
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }

                return $e->getResponse();
            })->then(static function (string $body) use ($slug): ?string {
                $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                $error = $json['error'] ?? null;
                if ($error !== 'closed') {
                    throw new RuntimeException(
                        sprintf('%s is not found on wordpress.org.', $slug)
                    );
                }

                $description = $json['description'] ?? null;
                if (! is_string($description)) {
                    return self::DEFAULT_CLOSED_DESCRIPTION;
                }
                if (empty($description)) {
                    return self::DEFAULT_CLOSED_DESCRIPTION;
                }

                return $description;
            });
    }
}
