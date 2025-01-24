<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser;

/**
 * Extract slug from download URLs.
 *
 * Download URLs look like:
 *   - https://downloads.wordpress.org/plugin/my-awesome-plugin.1.2.3.zip
 *   - https://downloads.wordpress.org/plugin/my-awesome-plugin.1.0-beta.zip
 *   - https://downloads.wordpress.org/plugin/my-awesome-plugin.zip
 *   - https://downloads.wordpress.org/plugins/my-awesome-plugin.zip
 *   - https://downloads.wordpress.org/plugins/myawesomeplugin.zip
 *   - https://downloads.wordpress.org/plugins/myawesomeplugin.zip/
 */
readonly class DownloadUrlParser implements UrlParserInterface
{
    private const string HOST = 'downloads.wordpress.org';

    public function slug(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== self::HOST) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $path = rtrim($path, '/');

        if (! str_ends_with($path, '.zip')) {
            return null;
        }
        if (substr_count($path, '/') !== 2) {
            return null;
        }

        [, $dir, $zip] = explode('/', $path);

        if ($dir !== 'plugin' && $dir !== 'plugins') {
            return null;
        }

        [$slug] = explode('.', $zip);

        return empty($slug) ? null : $slug;
    }
}
