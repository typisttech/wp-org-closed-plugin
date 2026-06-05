<?php

declare(strict_types=1);

namespace Tests\Feature\WpPackages\Api;

use Composer\Cache as ComposerCache;
use Composer\Downloader\TransportException;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Mockery;
use TypistTech\WpOrgClosedPlugin\WpPackages\Api\Client;

covers(Client::class);

const ENDPOINT = 'https://wp-packages.org/api/packages/wp-plugin/closed';

/**
 * Build a Response as the endpoint would return it.
 */
function endpointResponse(int $code, ?string $body): Response
{
    return new Response(['url' => ENDPOINT], $code, [], $body);
}

describe(Client::class, static function (): void {
    describe('::isClosed()', static function (): void {
        it('returns true if and only if the plugin is closed', function (string $slug, bool $expected): void {
            $httpDownloader = $this->loop()->getHttpDownloader();

            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();
            $cache->allows()->getAge('closed.json')->andReturnFalse();
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
            $cache = Mockery::spy(ComposerCache::class);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed($slug))->toBeFalse();

            $httpDownloader->shouldNotHaveReceived('get');
            $cache->shouldNotHaveReceived('read');
        })->with([
            'empty' => [''],
            'whitespace' => ['      '],
        ]);

        it('revalidates with a derived If-Modified-Since and serves the cache on 304', function (): void {
            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturn('["spam-stopgap", "better-delete-revision"]');
            $cache->allows()->getAge('closed.json')->andReturn(120);

            $captured = null;
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, Mockery::capture($captured))
                ->once()
                ->andReturn(endpointResponse(304, ''));

            $before = time();
            $client = new Client($httpDownloader, $cache);
            $isClosed = $client->isClosed('spam-stopgap');
            $isOpen = $client->isClosed('hello-dolly');
            $after = time();

            expect($isClosed)->toBeTrue()
                ->and($isOpen)->toBeFalse();

            // The marker is derived from time() during the call: accept any
            // second the call could have observed to avoid a clock-tick race.
            $acceptable = [];
            for ($now = $before; $now <= $after; $now++) {
                $acceptable[] = 'If-Modified-Since: ' . gmdate('D, d M Y H:i:s', $now - 120) . ' GMT';
            }
            expect(array_intersect($captured['http']['header'], $acceptable))->not->toBeEmpty();

            $cache->shouldNotHaveReceived('write');
        });

        it('writes the fetched body to cache on a cache miss', function (): void {
            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();
            $cache->allows()->getAge('closed.json')->andReturnFalse();
            $cache->expects()
                ->write('closed.json', '["spam-stopgap"]')
                ->once();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, [])
                ->once()
                ->andReturn(endpointResponse(200, '["spam-stopgap"]'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue();
        });

        it('resolves the closed list at most once', function (): void {
            $cache = Mockery::mock(ComposerCache::class);
            $cache->expects()->read('closed.json')->once()->andReturnFalse();
            $cache->allows()->getAge('closed.json')->andReturnFalse();
            $cache->allows()->write('closed.json', '["spam-stopgap"]');

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, [])
                ->once()
                ->andReturn(endpointResponse(200, '["spam-stopgap"]'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse()
                ->and($client->isClosed('spam-stopgap'))->toBeTrue();
        });

        it('fetches a changed list and overwrites the cache', function (): void {
            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturn('["spam-stopgap"]');
            $cache->allows()->getAge('closed.json')->andReturn(120);
            $cache->expects()
                ->write('closed.json', '["better-delete-revision"]')
                ->once();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, Mockery::any())
                ->andReturn(endpointResponse(200, '["better-delete-revision"]'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeTrue()
                ->and($client->isClosed('spam-stopgap'))->toBeFalse();
        });

        it('keeps using the cached list when the endpoint is unreachable', function (): void {
            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturn('["better-delete-revision"]');
            $cache->allows()->getAge('closed.json')->andReturn(120);

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, Mockery::any())
                ->andThrow(new TransportException('Connection refused'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('treats the plugin as not closed when unreachable with an empty cache', function (): void {
            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();
            $cache->allows()->getAge('closed.json')->andReturnFalse();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, [])
                ->andThrow(new TransportException('Connection refused'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('keeps the cached list and skips caching an unusable response', function (?string $body): void {
            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturn('["better-delete-revision"]');
            $cache->allows()->getAge('closed.json')->andReturn(120);

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, Mockery::any())
                ->andReturn(endpointResponse(200, $body));

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
            $cache = Mockery::mock(ComposerCache::class);
            $cache->allows()->read('closed.json')->andReturnFalse();
            $cache->allows()->getAge('closed.json')->andReturnFalse();
            $cache->expects()
                ->write('closed.json', '[]')
                ->once();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(ENDPOINT, [])
                ->once()
                ->andReturn(endpointResponse(200, '[]'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeFalse();
        });
    });
});
