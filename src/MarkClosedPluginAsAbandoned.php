<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin;

use Composer\Cache as ComposerCache;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\DownloadUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\MultiUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\SvnUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\UrlParserInterface;
use TypistTech\WpOrgClosedPlugin\WpPackages\Api\Client;

readonly class MarkClosedPluginAsAbandoned
{
    public static function create(Composer $composer, IOInterface $io): self
    {
        $config = $composer->getConfig();
        $cachePath = "{$config->get('cache-dir')}/wp-org-closed-plugin";
        $composerCache = new ComposerCache($io, $cachePath);
        $composerCache->setReadOnly($config->get('cache-read-only'));

        return new self(
            new MultiUrlParser(
                new DownloadUrlParser,
                new SvnUrlParser,
            ),
            new Client(
                $composer->getLoop()->getHttpDownloader(),
                $composerCache,
            ),
            $io,
        );
    }

    public function __construct(
        private UrlParserInterface $urlParser,
        private Client $client,
        private IOInterface $io,
    ) {}

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
