<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreCommandRunEvent;

class Main implements EventSubscriberInterface, PluginInterface
{
    private MarkClosedPluginAsAbandoned $markClosedAsAbandoned;

    private WarnLocked $warnLocked;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->markClosedAsAbandoned = MarkClosedPluginAsAbandoned::create($composer, $io);

        $this->warnLocked = new WarnLocked($io);
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
            PackageEvents::PRE_PACKAGE_INSTALL => ['markClosedAsAbandoned', PHP_INT_MAX - 1000],
            PackageEvents::PRE_PACKAGE_UPDATE => ['markClosedAsAbandoned', PHP_INT_MAX - 1000],

            PluginEvents::PRE_COMMAND_RUN => ['warnLocked', PHP_INT_MAX - 1000],
        ];
    }

    public function markClosedAsAbandoned(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        $package = match (true) {
            $operation instanceof InstallOperation => $operation->getPackage(),
            $operation instanceof UpdateOperation => $operation->getTargetPackage(),
            default => null,
        };

        if (! $package instanceof CompletePackageInterface) {
            return;
        }

        $this->markClosedAsAbandoned->__invoke($package);
    }

    public function warnLocked(PreCommandRunEvent $event): void
    {
        $this->warnLocked->__invoke($event);
    }
}
