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

        $content = (string) $this->cache->read($key);

        return match (true) {
            str_starts_with($content, 'closed'.PHP_EOL) => true,
            str_starts_with($content, 'open'.PHP_EOL) => false,
            default => null, // Unexpected content. Treat as a miss.
        };
    }

    public function write(string $slug, bool $isClosed): void
    {
        if ($this->cache->isReadOnly()) {
            return;
        }

        $content = $isClosed ? 'closed' : 'open';
        $content .= PHP_EOL;
        $content .= $slug;
        $content .= PHP_EOL;
        $content .= date(DateTimeInterface::RFC3339);
        $content .= PHP_EOL;

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
