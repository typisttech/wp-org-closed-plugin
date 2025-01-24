<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin\WPOrg\UrlParser;

/**
 * Extract slug from dist URL.
 *
 * Dist URLs look like:
 *   - https://plugins.svn.wordpress.org/my-awesome-plugin
 *   - https://plugins.svn.wordpress.org/myawesomeplugin
 */
readonly class SvnUrlParser implements UrlParserInterface
{
    private const string HOST = 'plugins.svn.wordpress.org';

    public function slug(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== self::HOST) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        [, $slug] = explode('/', $path);

        return empty($slug) ? null : $slug;
    }
}
