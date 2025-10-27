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
use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreCommandRunEvent;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;

class Plugin implements EventSubscriberInterface, PluginInterface
{
    /** @var string[] */
    private array $marked = [];

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
            PackageEvents::PRE_PACKAGE_INSTALL => [
                ['warmCache', PHP_INT_MAX - 500],
                ['markClosedAsAbandoned', PHP_INT_MAX - 1000],
            ],
            PackageEvents::PRE_PACKAGE_UPDATE => [
                ['warmCache', PHP_INT_MAX - 500],
                ['markClosedAsAbandoned', PHP_INT_MAX - 1000],
            ],

            ScriptEvents::POST_INSTALL_CMD => ['markClosedLockedPackagesIfNotAlready', PHP_INT_MAX - 1000],
            ScriptEvents::POST_UPDATE_CMD => ['markClosedLockedPackagesIfNotAlready', PHP_INT_MAX - 1000],

            PluginEvents::PRE_COMMAND_RUN => ['warnLocked', PHP_INT_MAX - 1000],
        ];
    }

    public function warmCache(PackageEvent $event): void
    {
        $packages = array_map(
            static fn ($operation) => match (true) {
                $operation instanceof InstallOperation => $operation->getPackage(),
                $operation instanceof UpdateOperation => $operation->getTargetPackage(),
                default => null,
            },
            $event->getOperations(),
        );
        $packages = array_filter($packages, static fn ($package) => $package instanceof CompletePackageInterface);
        $packages = array_filter($packages, static fn ($package) => ! $package->isAbandoned());

        $this->markClosedAsAbandoned->warmCache(...$packages);
    }

    public function markClosedAsAbandoned(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        $package = match (true) {
            $operation instanceof InstallOperation => $operation->getPackage(),
            $operation instanceof UpdateOperation => $operation->getTargetPackage(),
            default => null,
        };

        if (! $package instanceof CompletePackageInterface || $package->isAbandoned()) {
            return;
        }

        $prettyName = $package->getPrettyName();
        if (in_array($prettyName, $this->marked, true)) {
            return;
        }

        $this->marked[] = $prettyName;
        $this->markClosedAsAbandoned->__invoke($package);
    }

    public function markClosedLockedPackagesIfNotAlready(ScriptEvent $event): void
    {
        $lockedRepository = $event->getComposer()
            ->getLocker()
            ->getLockedRepository(true);

        $packages = $lockedRepository->getPackages();
        $packages = array_filter($packages, static fn ($package) => $package instanceof CompletePackage);
        $packages = array_filter($packages, static fn ($package) => ! $package->isAbandoned());
        $packages = array_filter(
            $packages,
            fn ($package) => ! in_array($package->getPrettyName(), $this->marked, true),
        );

        $this->markClosedAsAbandoned->warmCache(...$packages);

        foreach ($packages as $package) {
            $this->marked[] = $package->getPrettyName();
            $this->markClosedAsAbandoned->__invoke($package);
        }
    }

    public function warnLocked(PreCommandRunEvent $event): void
    {
        $this->warnLocked->__invoke($event);
    }
}
