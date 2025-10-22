<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\Api;

use Composer\Cache as ComposerCache;
use DateTimeInterface;

class Cache
{
    private const int TTL = 600;

    public function __construct(
        private readonly ComposerCache $cache,
    ) {}

    public function read(string $slug): ?bool
    {
        $key = $this->key($slug);
        $age = $this->cache->getAge($key);

        // Missed or expired.
        if ($age === false || $age > self::TTL) {
            return null;
        }

        $content = $this->cache->read($key);

        // Unexpected.
        if ($content === false) {
            return null;
        }

        $lines = explode("\n", $content);

        return $lines[0] === 'closed';
    }

    public function write(string $slug, bool $isClosed): void
    {
        if ($this->cache->isReadOnly()) {
            return;
        }

        $content = sprintf(
            "%s\n%s\n%s\n",
            $isClosed ? 'closed' : 'open',
            $slug,
            date(DateTimeInterface::RFC3339),
        );

        $this->cache->write(
            $this->key($slug),
            $content,
        );
    }

    private function key(string $slug): string
    {
        return "{$slug}.txt";
    }
}
