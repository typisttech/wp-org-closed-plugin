<?php

declare(strict_types=1);

namespace Tests\Unit\WpOrg\UrlParser;

use Composer\Cache as ComposerCache;
use Mockery;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\Cache;

covers(Cache::class);

describe(Cache::class, static function (): void {
    dataset('slugs', static function (): array {
        return [
            // Closed.
            'unused_permanent' => ['spam-stopgap', true, 'closed'],
            // Not closed.
            'open' => ['hello-dolly', false, 'open'],
        ];
    });

    describe('::read()', static function (): void {
        it('reads the first line', function (): void {});

        test('when missed', function (): void {});

        test('when expired', function (): void {});

        test('when unexpected content', function (): void {});
    });

    describe('::write()', static function (): void {
        it('writes the result into {$slug}.txt as the first line', function (string $slug, bool $isClosed, string $expected): void {
            $composerCache = Mockery::spy(ComposerCache::class);
            $cache = new Cache($composerCache);

            $cache->write($slug, $isClosed);

            $composerCache->shouldHaveReceived('write', [
                "{$slug}.txt",
                Mockery::on(static fn (string $actual) => str_starts_with($actual, $expected."\n")),
            ]);
        })->with('slugs');

        test('when composer cache is read only', function (string $slug, bool $isClosed): void {
            $composerCache = Mockery::spy(ComposerCache::class);
            $composerCache->allows()
                ->isReadOnly()
                ->andReturnTrue();

            $cache = new Cache($composerCache);

            $cache->write($slug, $isClosed);

            $composerCache->shouldNotHaveReceived('write');
        })->with('slugs');
    });
})->only();
