<?php

declare(strict_types=1);

namespace Tests\Unit\WpOrg\Api;

use Mockery;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\CacheInterface;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\CacheProxy;

covers(CacheProxy::class);

describe(CacheProxy::class, static function (): void {
    describe('::read()', static function (): void {
        test('when fast hits', function (bool $fastValue): void {
            $fast = Mockery::spy(CacheInterface::class);
            $fast->expects()
                ->read()
                ->with('foo')
                ->andReturn($fastValue);

            $slow = Mockery::spy(CacheInterface::class);

            $proxy = new CacheProxy($fast, $slow);

            $actual = $proxy->read('foo');

            expect($actual)->toBe($fastValue);
            $slow->shouldNotHaveReceived('read');
            $fast->shouldNotHaveReceived('write');
            $slow->shouldNotHaveReceived('write');
        })->with([true, false]);

        test('when fast misses while slow hits', function (bool $slowValue): void {
            $fast = Mockery::spy(CacheInterface::class);

            $slow = Mockery::spy(CacheInterface::class);
            $slow->expects()
                ->read()
                ->with('foo')
                ->andReturn($slowValue);

            $proxy = new CacheProxy($fast, $slow);

            $actual = $proxy->read('foo');

            expect($actual)->toBe($slowValue);
            $fast->shouldHaveReceived('write', ['foo', $slowValue]);
            $slow->shouldNotHaveReceived('write');
        })->with([true, false]);

        test('when both fast and slow miss', function (): void {
            $fast = Mockery::spy(CacheInterface::class);
            $slow = Mockery::spy(CacheInterface::class);

            $proxy = new CacheProxy($fast, $slow);

            $actual = $proxy->read('foo');

            expect($actual)->toBeNull();
            $fast->shouldNotHaveReceived('write');
            $slow->shouldNotHaveReceived('write');
        });
    });

    describe('::write()', static function (): void {
        it('forwards write to both fast and slow', function (bool $isClosed): void {
            $fast = Mockery::spy(CacheInterface::class);
            $slow = Mockery::spy(CacheInterface::class);

            $proxy = new CacheProxy($fast, $slow);

            $proxy->write('foo', $isClosed);

            $fast->shouldHaveReceived('write', ['foo', $isClosed]);
            $slow->shouldHaveReceived('write', ['foo', $isClosed]);
        })->with([true, false]);
    });
});
