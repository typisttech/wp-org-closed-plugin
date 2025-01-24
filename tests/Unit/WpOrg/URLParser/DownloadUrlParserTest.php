<?php

declare(strict_types=1);

namespace Tests\Unit\WpOrg\UrlParser;

use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WPOrg\UrlParser\DownloadUrlParser;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WPOrg\UrlParser\UrlParserInterface;

covers(DownloadUrlParser::class);

describe(DownloadUrlParser::class, static function (): void {
    it('implements UrlParserInterface', function (): void {
        $parser = new DownloadUrlParser;

        expect($parser)->toBeInstanceOf(UrlParserInterface::class);
    });

    describe('::slug()', static function (): void {
        dataset('urls', static function (): array {
            return [
                // Singular with hyphens.
                ['https://downloads.wordpress.org/plugin/foo-bar.1.2.3.zip', 'foo-bar'],
                ['https://downloads.wordpress.org/plugin/foo-bar.1.0-beta.zip', 'foo-bar'],
                ['https://downloads.wordpress.org/plugin/foo-bar.zip', 'foo-bar'],
                ['https://downloads.wordpress.org/plugin/foo-bar.zip/', 'foo-bar'],

                // Plural with hyphens.
                ['https://downloads.wordpress.org/plugins/foo-bar.1.2.3.zip', 'foo-bar'],
                ['https://downloads.wordpress.org/plugins/foo-bar.1.0-beta.zip', 'foo-bar'],
                ['https://downloads.wordpress.org/plugins/foo-bar.zip', 'foo-bar'],
                ['https://downloads.wordpress.org/plugins/foo-bar.zip/', 'foo-bar'],

                // Singular without hyphens.
                ['https://downloads.wordpress.org/plugin/foobar.1.2.3.zip', 'foobar'],
                ['https://downloads.wordpress.org/plugin/foobar.1.0-beta.zip', 'foobar'],
                ['https://downloads.wordpress.org/plugin/foobar.zip', 'foobar'],
                ['https://downloads.wordpress.org/plugin/foobar.zip/', 'foobar'],

                // Plural with hyphens.
                ['https://downloads.wordpress.org/plugins/foobar.1.2.3.zip', 'foobar'],
                ['https://downloads.wordpress.org/plugins/foobar.1.0-beta.zip', 'foobar'],
                ['https://downloads.wordpress.org/plugins/foobar.zip', 'foobar'],
                ['https://downloads.wordpress.org/plugins/foobar.zip/', 'foobar'],

                // Invalid.
                ['https://downloads.wordpress.org/themes/twentytwentyfive.1.0.zip', null],
                ['https://downloads.wordpress.org/theme/twentytwentyfive.1.0.zip', null],
                ['https://wordpress.org/plugin/foo-bar.1.2.3.zip', null],
                ['https://wordpress.org/theme/twentytwentyfive.1.0.zip', null],
                ['https://downloads.wordpress.org/plugin/plugin/foo-bar.1.2.3.zip', null],
                ['https://downloads.wordpress.org/plugin/plugins/foo-bar.1.2.3.zip', null],
                ['https://downloads.wordpress.org/plugins/plugin/foo-bar.1.2.3.zip', null],
                ['https://downloads.wordpress.org/plugins/plugins/foo-bar.1.2.3.zip', null],
                ['https://downloads.wordpress.org/plugin', null],
                ['https://downloads.wordpress.org/plugin/', null],
                ['https://downloads.wordpress.org/plugins', null],
                ['https://downloads.wordpress.org/plugins/', null],
                ['https://downloads.wordpress.org/plugin/foo-bar', null],
                ['https://downloads.wordpress.org/plugin/foo-bar.1.2.3', null],
                ['https://downloads.wordpress.org', null],
                ['https://downloads.wordpress.org/', null],
            ];
        });

        it('parses the plugin slug', function (string $url, ?string $expected): void {
            $parser = new DownloadUrlParser;

            $actual = $parser->slug($url);

            expect($actual)->toBe($expected);
        })->with('urls');
    });
});
