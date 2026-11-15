<?php

declare(strict_types=1);

namespace Tests\Feature\WpOrg\Api;

use Composer\Downloader\TransportException;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Mockery;
use RuntimeException;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\ArrayCache;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\CacheInterface;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\Client;

use function React\Promise\reject;
use function React\Promise\resolve;

covers(Client::class);

describe(Client::class, function (): void {
    describe('::isClosed()', function (): void {
        it('interprets and caches HTTP responses', function (?int $status, ?string $body, bool $expected): void {
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            if ($status === 200) {
                $response = resolve(new Response(['url' => 'https://api.wordpress.org'], 200, [], $body));
            } else {
                $error = new TransportException;
                $error->setStatusCode($status);
                $error->setResponse($body);
                $response = reject($error);
            }

            $httpDownloader->expects()
                ->add(
                    'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=example-plugin',
                    ['ssl' => ['allow_self_signed' => true]],
                )
                ->andReturn($response);

            $cache = new ArrayCache;
            $client = new Client($httpDownloader, $this->loop(), $cache);

            expect($client->isClosed(' example-plugin '))->toBe($expected)
                ->and($cache->read('example-plugin'))->toBe($expected);
        })->with([
            'closed' => [404, '{"error":"closed"}', true],
            'open' => [200, '{"name":"example"}', false],
            'not found' => [404, '{"error":"not found"}', false],
            'malformed response' => [404, '{', false],
            'empty response' => [404, null, false],
            'server error' => [500, '{"error":"closed"}', false],
            'network error' => [null, null, false],
        ]);

        it('treats unexpected request errors as open', function (): void {
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->add(
                    'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=example-plugin',
                    Mockery::any(),
                )
                ->andReturn(reject(new RuntimeException('Request failed')));
            $cache = new ArrayCache;
            $client = new Client($httpDownloader, $this->loop(), $cache);

            expect($client->isClosed('example-plugin'))->toBeFalse()
                ->and($cache->read('example-plugin'))->toBeFalse();
        });

        it('does not request or cache an empty slug', function (string $slug): void {
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $cache = Mockery::mock(CacheInterface::class);
            $client = new Client($httpDownloader, $this->loop(), $cache);

            expect($client->isClosed($slug))->toBeFalse();
        })->with(['', '      ']);

        it('reads from cache without making a request', function (bool $expected): void {
            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $cache = Mockery::mock(CacheInterface::class);
            $cache->expects()->read()->with('example-plugin')->andReturn($expected);
            $client = new Client($httpDownloader, $this->loop(), $cache);

            expect($client->isClosed('example-plugin'))->toBe($expected);
        })->with([true, false]);
    });

    describe('::warmCache()', function (): void {
        it('requests nonempty uncached slugs and populates the cache', function (): void {
            $error = new TransportException;
            $error->setStatusCode(404);
            $error->setResponse('{"error":"closed"}');

            $httpDownloader = Mockery::mock(HttpDownloader::class);
            $httpDownloader->expects()
                ->add('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=closed', Mockery::any())
                ->andReturn(reject($error));
            $httpDownloader->expects()
                ->add('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=open', Mockery::any())
                ->andReturn(resolve(new Response(['url' => 'https://api.wordpress.org'], 200, [], '{}')));

            $cache = new ArrayCache;
            $cache->write('cached', true);
            $client = new Client($httpDownloader, $this->loop(), $cache);
            $client->warmCache(' closed ', '', '  ', 'open', 'cached');

            expect($cache->read('closed'))->toBeTrue()
                ->and($cache->read('open'))->toBeFalse()
                ->and($cache->read('cached'))->toBeTrue();
        });
    });
});
