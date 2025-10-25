<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\Api;

readonly class CacheProxy implements CacheInterface
{
    public function __construct(
        private CacheInterface $fast,
        private CacheInterface $slow,
    ) {}

    public function read(string $slug): ?bool
    {
        $result = $this->fast->read($slug);
        if ($result !== null) {
            return $result;
        }

        $result = $this->slow->read($slug);
        if ($result !== null) {
            $this->fast->write($slug, $result);
        }

        return $result;
    }

    public function write(string $slug, bool $isClosed): void
    {
        $this->fast->write($slug, $isClosed);
        $this->slow->write($slug, $isClosed);
    }
}
