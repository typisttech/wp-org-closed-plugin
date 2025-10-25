<?php

declare(strict_types=1);

namespace Tests\Unit\WpOrg\Api;

use TypistTech\WpOrgClosedPlugin\WpOrg\Api\ArrayCache;

covers(ArrayCache::class);

describe(ArrayCache::class, static function (): void {
    describe('::read()', static function (): void {
        test('hit', function (bool $expected): void {
            $cache = new ArrayCache;
            $cache->write('foo', $expected);

            $actual = $cache->read('foo');

            expect($actual)->toBe($expected);
        })->with([true, false]);

        test('miss', function (): void {
            $cache = new ArrayCache;

            $actual = $cache->read('foo');

            expect($actual)->toBeNull();
        });
    });

    describe('::write()', static function (): void {
        it('stores', function (bool $isClosed): void {
            $cache = new ArrayCache;

            $cache->write('foo', $isClosed);

            expect($cache->read('foo'))->toBe($isClosed);
        })->with([true, false]);

        test('last write wins', function (bool $first, bool $last): void {
            $cache = new ArrayCache;

            $cache->write('foo', $first);
            $cache->write('foo', $last);

            expect($cache->read('foo'))->toBe($last);
        })->with([
            [true, false],
            [false, true],
            [true, true],
            [false, false],
        ]);
    });
});
