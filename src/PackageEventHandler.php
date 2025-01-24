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
}
