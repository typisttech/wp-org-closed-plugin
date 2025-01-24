<?php

declare(strict_types=1);

namespace Tests\Feature\WpOrg\Api;

use Composer\Factory;
use Composer\IO\NullIO;
use Exception;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\Api\Client;

covers(Client::class);

describe(Client::class, static function (): void {
    describe('::asyncFetchClosedDescription()', static function (): void {
        dataset('closed', static function (): array {
            return [
                'be-media-from-production' => [
                    'be-media-from-production',
                    'This plugin has been closed as of October 14, 2024 and is not available for download. This closure is permanent. Reason: Author Request.',
                ],
                'better-delete-revision' => [
                    'better-delete-revision',
                    'This plugin has been closed as of August 26, 2022 and is not available for download. Reason: Security Issue.',
                ],
                'no-longer-in-directory' => [
                    'no-longer-in-directory',
                    'This plugin has been closed as of October 2, 2018 and is not available for download. This closure is permanent. Reason: Guideline Violation.',
                ],
                'paid-memberships-pro' => [
                    'paid-memberships-pro',
                    'This plugin has been closed as of October 17, 2024 and is not available for download. This closure is permanent. Reason: Author Request.',
                ],
            ];
        });

        it('fetches the closed description', function (string $slug, ?string $expected): void {
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

            $promise = $client->asyncFetchClosedDescription($slug)
                ->then(function (?string $actual) use ($result): void {
                    $result->actual = $actual;
                });

            $loop->wait([$promise]);

            expect($result->actual)->toBe($expected);
        })->with('closed');

        dataset('not_closed', static function (): array {
            return [
                'open' => ['hello-dolly'],
                'not_found' => ['not-found-foo-bar-baz-qux'],
                'empty_slug' => [''],
                'whitespace' => ['      '],
            ];
        });

        it('throws exceptions', function (string $slug): void {
            $loop = Factory::create(
                new NullIO,
                null,
                true,
                true,
            )->getLoop();

            $client = new Client(
                $loop->getHttpDownloader()
            );

            $promise = $client->asyncFetchClosedDescription($slug);

            expect(
                static fn () => $loop->wait([$promise])
            )->toThrow(Exception::class);
        })->with('not_closed');
    });
});
