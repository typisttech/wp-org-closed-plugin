<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Util\Loop;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\Client;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\DownloadUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\MultiUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\SvnUrlParser;
use TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser\UrlParserInterface;

readonly class MarkClosedPluginAsAbandoned
{
    public static function create(Composer $composer, IOInterface $io): self
    {
        $loop = $composer->getLoop();

        return new self(
            new MultiUrlParser(
                new DownloadUrlParser,
                new SvnUrlParser,
            ),
            new Client(
                $loop->getHttpDownloader(),
            ),
            $loop,
            $io,
        );
    }

    public function __construct(
        private UrlParserInterface $urlParser,
        private Client $client,
        private Loop $loop,
        private IOInterface $io,
    ) {}

    public function __invoke(CompletePackageInterface $package): void
    {
        $this->io->debug(
            "Checking whether <info>{$package->getPrettyName()}</info> is closed on <href=https://wordpress.org>wordpress.org</>"
        );

        if ($package->isAbandoned()) {
            $this->io->debug("Skip checking for <info>{$package->getPrettyName()}</info> because it is already abandoned");

            return;
        }

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
            "Assuming <info>{$package->getPrettyName()}</info>'s <href=https://wordpress.org/plugins/{$slug}>wordpress.org</> slug is <comment>{$slug}</comment>"
        );

        $promise = $this->client->isClosedAsync($slug)
            ->then(function (bool $isClosed) use ($package, $slug): null {
                if (! $isClosed) {
                    return null;
                }

                if ($package->isAbandoned()) {
                    $this->io->debug("Skip marking <info>{$package->getPrettyName()}</info> as abandoned because it is already abandoned.");

                    return null;
                }

                $this->io->debug(
                    "Marking <info>{$package->getPrettyName()}</info> as <error>abandoned</error> because <href=https://wordpress.org/plugins/{$slug}/>wordpress.org/plugins/{$slug}</> has been closed"
                );
                $package->setAbandoned(true);
                $this->io->warning(
                    "Package {$package->getPrettyName()} is abandoned, you should avoid using it. No replacement was suggested."
                );

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
