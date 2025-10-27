<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\Api;

class ArrayCache implements CacheInterface
{
    /** @var array<string, bool> */
    private array $data = [];

    public function read(string $slug): ?bool
    {
        return $this->data[$slug] ?? null;
    }

    public function write(string $slug, bool $isClosed): void
    {
        $this->data[$slug] = $isClosed;
    }
}
