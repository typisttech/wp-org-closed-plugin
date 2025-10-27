<?php

declare(strict_types=1);

namespace Tests\Unit\WpOrg\UrlParser;

use Composer\Cache as ComposerCache;
use Mockery;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\CacheInterface;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\FileCache;

covers(FileCache::class);

describe(FileCache::class, static function (): void {
    it('implements CacheInterface', function (): void {
        $cache = new FileCache(
            Mockery::mock(ComposerCache::class),
        );

        expect($cache)->toBeInstanceOf(CacheInterface::class);
    });

    dataset('slugs', static function (): array {
        return [
            // Closed.
            'unused_permanent' => ['spam-stopgap', true, 'closed'],
            // Not closed.
            'open' => ['hello-dolly', false, 'open'],
        ];
    });

    describe('::read()', static function (): void {
        it('reads the first line of from {$slug}.txt',
            function (string $slug, bool $expected, string $firstLine): void {
                $composerCache = Mockery::mock(ComposerCache::class);
                $composerCache->expects()
                    ->getAge()
                    ->with("{$slug}.txt")
                    ->andReturn(123);
                $composerCache->expects()
                    ->read()
                    ->with("{$slug}.txt")
                    ->andReturn($firstLine.PHP_EOL.$slug.PHP_EOL.'2006-01-02T15:04:05+07:00'.PHP_EOL);

                $cache = new FileCache($composerCache);

                $actual = $cache->read($slug);

                expect($actual)->toBe($expected);
            })->with('slugs');

        test('when missed or expired', function (false|int $age): void {
            $composerCache = Mockery::mock(ComposerCache::class);
            $composerCache->expects()
                ->getAge()
                ->with('foo.txt')
                ->andReturn($age);

            $cache = new FileCache($composerCache);

            $actual = $cache->read('foo');

            expect($actual)->toBeNull();
        })->with([
            'missed' => false,
            'expired' => 999_999,
        ]);

        test('when unexpected content', function (false|string $content): void {
            $composerCache = Mockery::mock(ComposerCache::class);
            $composerCache->expects()
                ->getAge()
                ->with('foo.txt')
                ->andReturn(123);
            $composerCache->expects()
                ->read()
                ->with('foo.txt')
                ->andReturn($content);

            $cache = new FileCache($composerCache);

            $actual = $cache->read('foo');

            expect($actual)->toBeNull();
        })->with([
            'missed' => false,
            'unexpected' => 'not-closed-nor-open',
        ]);
    });

    describe('::write()', static function (): void {
        it('writes the result into {$slug}.txt as the first line',
            function (string $slug, bool $isClosed, string $expected): void {
                $composerCache = Mockery::spy(ComposerCache::class);
                $cache = new FileCache($composerCache);

                $cache->write($slug, $isClosed);

                $composerCache->shouldHaveReceived('write', [
                    "{$slug}.txt",
                    Mockery::on(static fn (string $actual) => str_starts_with($actual, $expected.PHP_EOL.$slug.PHP_EOL)),
                ]);
            })->with('slugs');

        test('when composer cache is read only', function (string $slug, bool $isClosed): void {
            $composerCache = Mockery::spy(ComposerCache::class);
            $composerCache->expects()
                ->isReadOnly()
                ->andReturnTrue();

            $cache = new FileCache($composerCache);

            $cache->write($slug, $isClosed);

            $composerCache->shouldNotHaveReceived('write');
        })->with('slugs');
    });
});
