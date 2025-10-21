<?php

declare(strict_types=1);

namespace Tests\Feature\WpOrg\Api;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Util\HttpDownloader;
use Mockery;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\Client;

use function React\Promise\resolve;

covers(Client::class);

describe(Client::class, static function (): void {
    describe('::isClosed()', static function (): void {
        dataset('slugs', static function (): array {
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

        it('returns true if and only if the plugin is closed', function (string $slug, bool $expected): void {
            $loop = Factory::create(new NullIO, null, true, true)
                ->getLoop();

            $client = new Client(
                $loop->getHttpDownloader(),
                $loop,
            );

            $actual = $client->isClosed($slug);

            expect($actual)->toBe($expected);
        })->with('slugs');

        it('caches HTTP responses in memory', function (bool $isClosed): void {
            $loop = Factory::create(new NullIO, null, true, true)
                ->getLoop();

            $httpDownloaderMock = Mockery::mock(HttpDownloader::class);
            $httpDownloaderMock->allows()
                ->add()
                ->withAnyArgs()
                ->andReturn(
                    resolve($isClosed)
                );

            $client = new Client($httpDownloaderMock, $loop);

            $client->isClosed('foo');
            $client->isClosed('foo');

            $httpDownloaderMock->shouldHaveReceived('add')->once();
        })->with([true, false]);
    });
});
