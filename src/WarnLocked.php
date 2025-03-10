<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin;

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

        $this->io->warning('Skipped checking for closed plugins because of --locked.');
    }
}
