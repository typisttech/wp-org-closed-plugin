<?php

declare(strict_types=1);

namespace Tests\Feature\WpOrg\Api;

use Composer\Util\HttpDownloader;
use Mockery;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\CacheInterface;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\Client;

covers(Client::class);

describe(Client::class, static function (): void {
    describe('::isClosed()', static function (): void {
        dataset('many_slugs', static function (): array {
            return [
                // Closed.
                'author_request_permanent' => ['paid-memberships-pro', true],
                'author_request_permanent_2' => ['be-media-from-production', true],
                'guideline_violation' => ['text-control', true],
                'guideline_violation_permanent' => ['no-longer-in-directory', true],
                'licensing-trademark-violation' => ['tiutiu-facebook-friends-widget', true],
                'security_issue' => ['better-delete-revision', true],
                'security_issue_2' => ['browser-bookmark', true],
                'unknown' => ['rumgallery', true],
                'unknown_permanent' => ['link-linker', true],
                'unused' => ['auto-translator', true],
                'unused_permanent' => ['spam-stopgap', true],
                'unused_permanent_2' => ['update-linkroll', true],

                // Not closed.
                'open' => ['hello-dolly', false],
                'not_found' => ['not-found-foo-bar-baz-qux', false],
                'empty_slug' => ['', false],
                'whitespace' => ['      ', false],
            ];
        });

        dataset('slugs', static function (): array {
            return [
                // Closed.
                'unused_permanent' => ['spam-stopgap', true],
                // Not closed.
                'open' => ['hello-dolly', false],
            ];
        });

        it('returns true if and only if the plugin is closed', function (string $slug, bool $expected): void {
            $loop = $this->loop();
            $httpDownloader = $loop->getHttpDownloader();
            $cache = Mockery::spy(CacheInterface::class);

            $client = new Client($httpDownloader, $loop, $cache);

            $actual = $client->isClosed($slug);

            expect($actual)->toBe($expected);
        })->with('many_slugs');

        it('writes to cache', function (string $slug, bool $expected): void {
            $loop = $this->loop();
            $httpDownloader = $loop->getHttpDownloader();
            $cache = Mockery::spy(CacheInterface::class);

            $client = new Client($httpDownloader, $loop, $cache);

            $actual = $client->isClosed($slug);

            $cache->shouldHaveReceived('write', [$slug, $expected]);
            expect($actual)->toBe($expected);
        })->with('slugs');

        it('reads from cache', function (string $slug, bool $expected): void {
            $loop = $this->loop();
            $httpDownloader = Mockery::spy(HttpDownloader::class);

            $cache = Mockery::spy(CacheInterface::class);
            $cache->expects()
                ->read()
                ->with($slug)
                ->andReturn($expected);

            $client = new Client($httpDownloader, $loop, $cache);

            $actual = $client->isClosed($slug);

            $httpDownloader->shouldNotHaveReceived('add');
            $cache->shouldNotHaveReceived('write');
            expect($actual)->toBe($expected);
        })->with('slugs');
    });
});
