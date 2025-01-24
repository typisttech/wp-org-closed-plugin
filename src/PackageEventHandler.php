<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Package\CompletePackageInterface;
use Composer\Util\Loop;
use React\Promise\PromiseInterface;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\Api\Client;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\UrlParserInterface;

readonly class PackageEventHandler
{
    public function __construct(
        private UrlParserInterface $urlParser,
        private Client $client,
        private Loop $loop,
    ) {}

    public function setAbandoned(PackageEvent $event): void
    {
        $promises = array_map(
            function (OperationInterface $operation): ?PromiseInterface {
                $package = $this->package($operation);
                if ($package === null) {
                    return null;
                }
                if ($package->isAbandoned()) {
                    return null;
                }

                $slug = $this->slug(
                    ...$package->getDistUrls(),
                    ...$package->getSourceUrls(),
                );
                if ($slug === null) {
                    return null;
                }

                return $this->client->isClosedAsync($slug)
                    ->then(static function (bool $isClosed) use ($package): void {
                        if (! $isClosed || $package->isAbandoned()) {
                            return;
                        }

                        $package->setAbandoned(true);
                    })->catch(static fn () => null);
            },
            $event->getOperations(),
        );
        $promises = array_filter($promises);

        $this->loop->wait($promises);
    }

    private function package(OperationInterface $operation): ?CompletePackageInterface
    {
        $package = match (true) {
            $operation instanceof InstallOperation => $operation->getPackage(),
            $operation instanceof UpdateOperation => $operation->getTargetPackage(),
            default => null,
        };

        return $package instanceof CompletePackageInterface ? $package : null;
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
