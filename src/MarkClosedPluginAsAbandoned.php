<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin;

use Composer\Cache;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\ArrayCache;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\CacheProxy;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\Client;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\FileCache;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\DownloadUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\MultiUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\SvnUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\UrlParserInterface;

readonly class MarkClosedPluginAsAbandoned
{
    public static function create(Composer $composer, IOInterface $io): self
    {
        $loop = $composer->getLoop();

        $config = $composer->getConfig();
        $cachePath = "{$config->get('cache-dir')}/wp-org-closed-plugin";
        $composerCache = new Cache($io, $cachePath);
        $composerCache->setReadOnly($config->get('cache-read-only'));
        $cache = new CacheProxy(
            new ArrayCache,
            new FileCache($composerCache),
        );

        return new self(
            new MultiUrlParser(
                new DownloadUrlParser,
                new SvnUrlParser,
            ),
            new Client(
                $loop->getHttpDownloader(),
                $loop,
                $cache,
            ),
            $io,
        );
    }

    public function __construct(
        private UrlParserInterface $urlParser,
        private Client $client,
        private IOInterface $io,
    ) {}

    public function warmCache(CompletePackageInterface ...$packages): void
    {
        $slugs = array_map(
            fn (CompletePackageInterface $package): ?string => $this->slug(
                ...$package->getDistUrls(),
                ...$package->getSourceUrls(),
            ),
            $packages,
        );
        $slugs = array_filter($slugs, static fn (?string $slug) => $slug !== null);

        $slugCount = count($slugs);
        $message = sprintf(
            'Warming cache for WordPress.org closed plugin status for %d %s',
            $slugCount,
            $slugCount === 1 ? 'slug' : 'slugs',
        );
        $this->io->debug($message);

        $this->client->warmCache(...$slugs);
    }

    public function __invoke(CompletePackageInterface $package): void
    {
        $this->io->debug(
            "Checking whether <info>{$package->getPrettyName()}</info> is closed on https://wordpress.org"
        );

        $slug = $this->slug(
            ...$package->getDistUrls(),
            ...$package->getSourceUrls(),
        );
        if ($slug === null) {
            $this->io->debug(
                "<info>{$package->getPrettyName()}</info> is not a <href=https://wordpress.org>wordpress.org</> plugin"
            );

            return;
        }
        $this->io->debug(
            "Assuming <info>{$package->getPrettyName()}</info> is <href=https://wordpress.org/plugins/{$slug}>https://wordpress.org/plugins/<comment>{$slug}</comment></>"
        );

        $isClosed = $this->client->isClosed($slug);
        if (! $isClosed) {
            return;
        }

        $package->setAbandoned(true);
        $this->io->warning(
            "Package {$package->getPrettyName()} is abandoned because https://wordpress.org/plugins/{$slug} has been closed, you should avoid using it. No replacement was suggested."
        );
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
