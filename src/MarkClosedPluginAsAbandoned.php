<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin;

use Composer\Package\CompletePackageInterface;
use Composer\Util\Loop;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\Api\Client;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\DownloadUrlParser;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\MultiUrlParser;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\SvnUrlParser;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\UrlParserInterface;

readonly class MarkClosedPluginAsAbandoned
{
    public static function create(Loop $loop): self
    {
        return new self(
            new MultiUrlParser(
                new DownloadUrlParser,
                new SvnUrlParser,
            ),
            new Client(
                $loop->getHttpDownloader(),
            ),
            $loop,
        );
    }

    public function __construct(
        private UrlParserInterface $urlParser,
        private Client $client,
        private Loop $loop,
    ) {}

    public function __invoke(CompletePackageInterface $package): void
    {
        if ($package->isAbandoned()) {
            return;
        }

        $slug = $this->slug(
            ...$package->getDistUrls(),
            ...$package->getSourceUrls(),
        );
        if ($slug === null) {
            return;
        }

        $promise = $this->client->isClosedAsync($slug)
            ->then(static function (bool $isClosed) use ($package): null {
                if (! $package->isAbandoned()) {
                    $package->setAbandoned($isClosed);
                }

                return null;
            })->catch(static fn () => null);

        $this->loop->wait([$promise]);
    }

    private function slug(string ...$urls): ?string
    {
        foreach ($urls as $url) {
            $slug = $this->urlParser->slug($url);

            if ($slug !== null) {
                return $slug;
            }
        }

        return null;
    }
}
