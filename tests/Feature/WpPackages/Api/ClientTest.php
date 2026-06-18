<?php

declare(strict_types=1);

namespace Tests\Feature\WpPackages\Api;

use Composer\Cache;
use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use TypistTech\WpOrgClosedPlugin\WpPackages\Api\Client;

covers(Client::class);

describe(Client::class, static function (): void {
    describe('::isClosed()', static function (): void {
        it('returns true if and only if the plugin is closed', function (string $slug, bool $expected): void {
            $httpDownloader = Factory::createHttpDownloader(new NullIO, new Config(false, null));

            $cache = new Cache(new NullIO, '/dev/null');
            $cache->setReadOnly(true);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed($slug))->toBe($expected);
        })->group('network')->with([
            // Closed.
            'author_request_permanent' => ['paid-memberships-pro', true],
            'guideline_violation_permanent' => ['no-longer-in-directory', true],
            'security_issue' => ['better-delete-revision', true],
            'unused_permanent' => ['spam-stopgap', true],

            // Not closed.
            'open' => ['hello-dolly', false],
            'not_found' => ['not-found-foo-bar-baz-qux', false],
            'empty_slug' => ['', false],
            'whitespace' => ['      ', false],
        ]);
    });
});
