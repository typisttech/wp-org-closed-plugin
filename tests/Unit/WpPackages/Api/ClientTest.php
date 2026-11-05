<?php

declare(strict_types=1);

namespace Tests\Unit\WpPackages\Api;

use Composer\Cache;
use Composer\Downloader\TransportException;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Mockery;
use TypistTech\WpOrgClosedPlugin\WpPackages\Api\Client;

covers(Client::class);

describe(Client::class, static function (): void {
    describe('::isClosed()', static function (): void {
//        it('returns true if and only if the plugin is closed', function (string $slug, bool $expected): void {
//            $httpDownloader = Factory::create(new NullIO, null, true, true)
//                ->getLoop()
//                ->getHttpDownloader();
//
//            $cache = Mockery::mock(Cache::class);
//            $cache->allows()->read(Client::URL)->andReturnFalse();
//            $cache->allows()->write(Client::URL, Mockery::any());
//
//            $client = new Client($httpDownloader, $cache);
//
//            expect($client->isClosed($slug))->toBe($expected);
//        })->with([
//            // Closed.
//            'author_request_permanent' => ['paid-memberships-pro', true],
//            'guideline_violation_permanent' => ['no-longer-in-directory', true],
//            'security_issue' => ['better-delete-revision', true],
//            'unused_permanent' => ['spam-stopgap', true],
//
//            // Not closed.
//            'open' => ['hello-dolly', false],
//            'not_found' => ['not-found-foo-bar-baz-qux', false],
//            'empty_slug' => ['', false],
//            'whitespace' => ['      ', false],
//        ]);

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
            $cache->allows()->read(Client::URL)->andReturn('["spam-stopgap", "better-delete-revision"]');
            $cache->allows()->getAge(Client::URL)->andReturn(599);

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->shouldNotReceive('get');

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('writes the fetched body to cache on a cache miss', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read(Client::URL)->andReturnFalse();
            $cache->expects()
                ->write(Client::URL, '["spam-stopgap"]')
                ->once();

            $response = new Response(['url' => Client::URL], 200, [], '["spam-stopgap"]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue();
        });

        it('resolves the closed list at most once', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->expects()->read(Client::URL)->once()->andReturnFalse();
            $cache->allows()->write(Client::URL, '["spam-stopgap"]');

            $response = new Response(['url' => Client::URL], 200, [], '["spam-stopgap"]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse()
                ->and($client->isClosed('spam-stopgap'))->toBeTrue();
        });

        it('fetches a changed list from stale cache and overwrites the cache', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read(Client::URL)->andReturn('["spam-stopgap"]');
            $cache->allows()->getAge(Client::URL)->andReturn(600);
            $cache->expects()
                ->write(Client::URL, '["better-delete-revision"]')
                ->once();

            $response = new Response(['url' => Client::URL], 200, [], '["better-delete-revision"]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeTrue()
                ->and($client->isClosed('spam-stopgap'))->toBeFalse();
        });

        it('keeps using the stale cached list when the endpoint is unreachable', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read(Client::URL)->andReturn('["better-delete-revision"]');
            $cache->allows()->getAge(Client::URL)->andReturn(600);

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
                ->andThrow(new TransportException('Connection refused'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeTrue()
                ->and($client->isClosed('hello-dolly'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('treats the plugin as not closed when unreachable with an empty cache', function (): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read(Client::URL)->andReturnFalse();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
                ->andThrow(new TransportException('Connection refused'));

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('better-delete-revision'))->toBeFalse();

            $cache->shouldNotHaveReceived('write');
        });

        it('keeps the cached list and skips caching an unusable response', function (?string $body): void {
            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read(Client::URL)->andReturn('["better-delete-revision"]');
            $cache->allows()->getAge(Client::URL)->andReturn(600);

            $response = new Response(['url' => Client::URL], 200, [], $body);
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
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
            $cache->allows()->read(Client::URL)->andReturnFalse();
            $cache->expects()
                ->write(Client::URL, '[]')
                ->once();

            $response = new Response(['url' => Client::URL], 200, [], '[]');
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
                ->once()
                ->andReturn($response);

            $client = new Client($httpDownloader, $cache);

            expect($client->isClosed('spam-stopgap'))->toBeFalse();
        });

        it('dispatches file download events and uses the processed URL as the cache key', function (): void {
            $processedUrl = 'https://mirror.example.com/closed.json';
            $options = ['http' => ['header' => ['X-Mirror: 1']]];
            $response = new Response(['url' => $processedUrl], 200, [], '["mirrored-plugin"]');

            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read($processedUrl)->andReturnFalse();
            $cache->expects()
                ->write($processedUrl, '["mirrored-plugin"]')
                ->once();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get($processedUrl, $options)
                ->once()
                ->andReturn($response);

            $eventDispatcher = Mockery::mock(EventDispatcher::class);
            $eventDispatcher->expects()
                ->dispatch(PluginEvents::PRE_FILE_DOWNLOAD, Mockery::on(
                    static function (PreFileDownloadEvent $event) use ($processedUrl, $options): bool {
                        expect($event->getProcessedUrl())->toBe(Client::URL)
                            ->and($event->getType())->toBe('metadata');

                        $event->setProcessedUrl($processedUrl);
                        $event->setTransportOptions($options);

                        return true;
                    },
                ))
                ->once();
            $eventDispatcher->expects()
                ->dispatch(PluginEvents::POST_FILE_DOWNLOAD, Mockery::on(
                    static fn (PostFileDownloadEvent $event): bool => $event->getUrl() === $processedUrl
                        && $event->getType() === 'metadata'
                        && ($event->getContext()['response'] ?? null) === $response,
                ))
                ->once();

            $client = new Client($httpDownloader, $cache, $eventDispatcher);

            expect($client->isClosed('mirrored-plugin'))->toBeTrue();
        });

        it('uses a custom cache key from the pre file download event', function (): void {
            $customCacheKey = 'custom-closed-list.json';
            $response = new Response(['url' => Client::URL], 200, [], '["custom-cache-plugin"]');

            $cache = Mockery::mock(Cache::class);
            $cache->allows()->read($customCacheKey)->andReturnFalse();
            $cache->expects()
                ->write($customCacheKey, '["custom-cache-plugin"]')
                ->once();

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->get(Client::URL, [])
                ->once()
                ->andReturn($response);

            $eventDispatcher = Mockery::mock(EventDispatcher::class);
            $eventDispatcher->expects()
                ->dispatch(PluginEvents::PRE_FILE_DOWNLOAD, Mockery::on(
                    static function (PreFileDownloadEvent $event) use ($customCacheKey): bool {
                        $event->setCustomCacheKey($customCacheKey);

                        return true;
                    },
                ))
                ->once();
            $eventDispatcher->allows()
                ->dispatch(PluginEvents::POST_FILE_DOWNLOAD, Mockery::type(PostFileDownloadEvent::class));

            $client = new Client($httpDownloader, $cache, $eventDispatcher);

            expect($client->isClosed('custom-cache-plugin'))->toBeTrue();
        });
    });
});
