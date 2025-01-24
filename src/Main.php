<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreCommandRunEvent;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\DownloadUrlParser;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\MultiUrlParser;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser\SvnUrlParser;

class Main implements EventSubscriberInterface, PluginInterface
{
    private PackageEventHandler $packageEventHandler;

    private CommandEventHandler $commandEventHandler;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $loop = $composer->getLoop();

        $urlParser = new MultiUrlParser(
            new DownloadUrlParser,
            new SvnUrlParser,
        );

        $client = new WpOrg\Api\Client(
            $loop->getHttpDownloader(),
        );

        $this->packageEventHandler = new PackageEventHandler($urlParser, $client, $loop);
        $this->commandEventHandler = new CommandEventHandler($io);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Do nothing.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Do nothing.
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::PRE_PACKAGE_INSTALL => ['onPackageEvent', PHP_INT_MAX - 1000],
            PackageEvents::PRE_PACKAGE_UPDATE => ['onPackageEvent', PHP_INT_MAX - 1000],
            PluginEvents::PRE_COMMAND_RUN => ['onPreCommandRun', PHP_INT_MAX - 1000],
        ];
    }

    public function onPackageEvent(PackageEvent $event): void
    {
        $this->packageEventHandler->setAbandoned($event);
    }

    public function onPreCommandRun(PreCommandRunEvent $event): void
    {
        $this->commandEventHandler->warnLocked($event);
    }
}
