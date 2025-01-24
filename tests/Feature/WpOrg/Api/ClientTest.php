<?php

declare(strict_types=1);

namespace Tests\Feature\WpOrg\Api;

use Composer\Factory;
use Composer\IO\NullIO;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\Api\Client;

covers(Client::class);

describe(Client::class, static function (): void {
    describe('::isClosedAsync()', static function (): void {
        dataset('slugs', static function (): array {
            return [
                // Closed.
                'be_media_from_production' => ['be-media-from-production', true],
                'better_delete_revision' => ['better-delete-revision', true],
                'no_longer_in_directory' => ['no-longer-in-directory', true],
                'paid_memberships_pro' => ['paid-memberships-pro', true],

                // Not closed.
                'open' => ['hello-dolly', false],
                'not_found' => ['not-found-foo-bar-baz-qux', false],
                'empty_slug' => ['', false],
                'whitespace' => ['      ', false],
            ];
        });

        it('return true if and only if the plugin is closed', function (string $slug, bool $expected): void {
            $loop = Factory::create(
                new NullIO,
                null,
                true,
                true,
            )->getLoop();

            $client = new Client(
                $loop->getHttpDownloader()
            );

            $result = (object) [
                'actual' => null,
            ];

            $promise = $client->isClosedAsync($slug)
                ->then(function (mixed $actual) use ($result): void {
                    $result->actual = $actual;
                });

            $loop->wait([$promise]);

            expect($result->actual)->toBe($expected);
        })->with('slugs');
    });
});
