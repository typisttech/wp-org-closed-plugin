<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin;

use Composer\IO\IOInterface;
use Composer\Plugin\PreCommandRunEvent;

readonly class WarnLocked
{
    public function __construct(
        private IOInterface $io,
    ) {}

    public function __invoke(PreCommandRunEvent $event): void
    {
        if ($event->getCommand() !== 'audit') {
            return;
        }

        if (! $event->getInput()->getOption('locked')) {
            return;
        }

        $this->io->writeError(
            '<warning>typisttech\wpsecadvi-wp-org-closed-plugin skipped checking for closed plugins because the "--locked" option is in used.</warning>',
        );
    }
}
