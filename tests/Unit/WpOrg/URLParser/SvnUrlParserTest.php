<?php

declare(strict_types=1);

namespace Tests\Unit\WpOrg\UrlParser;

use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WPOrg\UrlParser\SvnUrlParser;
use TypistTech\WpSecAdvi\WpOrgClosedPlugin\WPOrg\UrlParser\UrlParserInterface;

covers(SvnUrlParser::class);

describe(SvnUrlParser::class, static function (): void {
    it('implements UrlParserInterface', function (): void {
        $parser = new SvnUrlParser;

        expect($parser)->toBeInstanceOf(UrlParserInterface::class);
    });

    describe('::slug()', static function (): void {
        dataset('urls', static function (): array {
            return [
                ['https://plugins.svn.wordpress.org/foo-bar/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar', 'foo-bar'],

                ['https://plugins.svn.wordpress.org/foo-bar/assets/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/assets', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/assets/screenshot-1.png', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/assets/screenshot-1.png/', 'foo-bar'],

                ['https://plugins.svn.wordpress.org/foo-bar/branches/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/branches', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/branches/baz/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/branches/baz', 'foo-bar'],

                ['https://plugins.svn.wordpress.org/foo-bar/tags/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/tags', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/tags/1.2.3/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/tags/1.2.3', 'foo-bar'],

                ['https://plugins.svn.wordpress.org/foo-bar/trunk/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/trunk', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/trunk/src/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/trunk/src', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/trunk/src/baz/', 'foo-bar'],
                ['https://plugins.svn.wordpress.org/foo-bar/trunk/src/baz', 'foo-bar'],

                // Invalid.
                ['https://themes.svn.wordpress.org/foo-bar/', null],
                ['https://themes.svn.wordpress.org/foo-bar', null],
                ['https://plugins.svn.wordpress.org/', null],
                ['https://plugins.svn.wordpress.org', null],
                ['https://svn.wordpress.org/', null],
                ['https://svn.wordpress.org', null],
                ['https://svn.wordpress.org/foo-bar/', null],
                ['https://svn.wordpress.org/foo-bar', null],
                ['https://wordpress.org/', null],
                ['https://wordpress.org', null],
                ['https://wordpress.org/foo-bar/', null],
                ['https://wordpress.org/foo-bar', null],
            ];
        });

        it('decode the plugin slug', function (string $url, ?string $expected): void {
            $parser = new SvnUrlParser;

            $actual = $parser->slug($url);

            expect($actual)->toBe($expected);
        })->with('urls');
    });
});
