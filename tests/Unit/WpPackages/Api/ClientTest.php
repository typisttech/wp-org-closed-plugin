<?php

declare(strict_types=1);

namespace Tests\Unit\WpPackages\Api;

use Composer\Cache;
use Composer\Downloader\TransportException;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Mockery;
use TypistTech\WpOrgClosedPlugin\WpPackages\Api\Client;

covers(Client::class);

describe(Client::class, static function (): void {
    describe('::isClosed()', static function (): void {
        it('returns true if and only if the plugin is closed', function (string $slug, bool $expected): void {
            $httpDownloader = Factory::create(new NullIO, null, true, true)
                ->getLoop()
                ->getHttpDownloader();

            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();
            $cache->allows()->write('closed.json', Mockery::any());

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

        it('returns false for an empty or whitespace-only slug without any I/O', function (string $slug): void {
            $httpDownloader = Mockery::spy(HttpDownloader::class);
            $cache = Mockery::spy(Cache::class);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed($slug))->toBeFalse();

            $httpDownloader->shouldNotHaveReceived('get');
            $cache->shouldNotHaveReceived('read');
        })->with([
            'empty' => [''],
            'whitespace' => ['      '],
        ]);

        it('uses fresh cache without sending an HTTP request', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturn('["spam-stopgap", "better-delete-revision"]');
            $cache->allows()->getAge('closed.json')->andReturn(599);

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->shouldNotReceive('get');

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('writes the fetched body to cache on a cache miss', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();
            $cache->expects()
                ->write('closed.json', '["spam-stopgap"]')
                ->once();

            $response = new Response(['url' => Client::URL], 200, [], '["spam-stopgap"]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL)
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue();
        });

        it('resolves the closed list at most once', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->expects()->read('closed.json')->once()->andReturnFalse();
            $cache->allows()->write('closed.json', '["spam-stopgap"]');

            $response = new Response(['url' => Client::URL], 200, [], '["spam-stopgap"]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL)
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse()
                ->and($client->isClosed('spam-stopgap'))->toBeTrue();
        });

        it('fetches a changed list from stale cache and overwrites the cache', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturn('["spam-stopgap"]');
            $cache->allows()->getAge('closed.json')->andReturn(600);
            $cache->expects()
                ->write('closed.json', '["better-delete-revision"]')
                ->once();

            $response = new Response(['url' => Client::URL], 200, [], '["better-delete-revision"]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL)
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeTrue()
                ->and($client->isClosed('spam-stopgap'))->toBeFalse();
        });

        it('keeps using the stale cached list when the endpoint is unreachable', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturn('["better-delete-revision"]');
            $cache->allows()->getAge('closed.json')->andReturn(600);

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL)
                ->andThrow(new TransportException('Connection refused'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('treats the plugin as not closed when unreachable with an empty cache', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL)
                ->andThrow(new TransportException('Connection refused'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('keeps the cached list and skips caching an unusable response', function (?string $body): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturn('["better-delete-revision"]');
            $cache->allows()->getAge('closed.json')->andReturn(600);

            $response = new Response(['url' => Client::URL], 200, [], $body);
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL)
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeTrue();

            $cache->shouldNotHaveReceived('write');
        })->with([
            'not json' => ['not-json'],
            'json object' => ['{"error": "better-delete-revision"}'],
            'json string' => ['"better-delete-revision"'],
            'empty body' => [''],
            'null body' => [null],
        ]);

        it('caches a legitimately empty closed list', function (): void {
            // A valid but empty `[]` is distinct from a malformed body: it is
            // cached and returned, rather than falling back.
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();
            $cache->expects()
                ->write('closed.json', '[]')
                ->once();

            $response = new Response(['url' => Client::URL], 200, [], '[]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL)
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeFalse();
        });
    });
});
