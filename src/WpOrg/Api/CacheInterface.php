<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\Api;

interface CacheInterface
{
    public function read(string $slug): ?bool;

    public function write(string $slug, bool $isClosed): void;
}
