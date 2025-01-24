<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Package\CompletePackageInterface;

readonly class PackageEventHandler
{
    private const string DOWNLOAD_HOST = 'downloads.wordpress.org';

    private const string SVN_HOST = 'plugins.svn.wordpress.org';

    public function setAbandoned(PackageEvent $event): void
    {
        foreach ($event->getOperations() as $operation) {
            $package = match (true) {
                $operation instanceof InstallOperation => $operation->getPackage(),
                $operation instanceof UpdateOperation => $operation->getTargetPackage(),
                default => null,
            };

            if (! $package instanceof CompletePackageInterface) {
                continue;
            }

            $urls = [
                ...$package->getDistUrls(),
                ...$package->getSourceUrls(),
            ];

            if (! $this->isWpOrg(...$urls)) {
                continue;
            }

            $http = $event->getComposer()->getLoop()->getHttpDownloader();
            $http->get($http);

            $package->setAbandoned('closed because of security issue');
        }
    }

    private function slug(string $url): ?string {}

    /**
     * Extract slug from dist URL.
     *
     * Dist URLs look like:
     *   - https://downloads.wordpress.org/plugin/my-awesome-plugin.1.2.3.zip
     *   - https://downloads.wordpress.org/plugin/my-awesome-plugin.1.0-beta.zip
     *   - https://downloads.wordpress.org/plugin/my-awesome-plugin.zip
     */
    private function slugFromDistUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== self::DOWNLOAD_HOST) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $pathParts = explode('/', $path);

        $dir = $pathParts[1] ?? '';
        if ($dir !== 'plugin') {
            return null;
        }

        $zip = $pathParts[2] ?? '';
        $zipParts = explode('.', $zip);

        return $zipParts[0] ?? null;
    }

    /**
     * Extract slug from source URL.
     *
     * Source URLs look like https://plugins.svn.wordpress.org/my-awesome-plugin/
     */
    private function slugFromSourceUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== self::SVN_HOST) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $pathParts = explode('/', $path);

        return $pathParts[1] ?? null;
    }
}
