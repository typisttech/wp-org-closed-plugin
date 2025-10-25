<div align="center">

# WP Org Closed Plugin

[![Packagist Version](https://img.shields.io/packagist/v/typisttech/wp-org-closed-plugin)](https://packagist.org/packages/typisttech/wp-org-closed-plugin)
[![Test](https://github.com/typisttech/wp-org-closed-plugin/actions/workflows/test.yml/badge.svg)](https://github.com/typisttech/wp-org-closed-plugin/actions/workflows/test.yml)
[![codecov](https://codecov.io/gh/typisttech/wp-org-closed-plugin/graph/badge.svg?token=NCXHH990CY)](https://codecov.io/gh/typisttech/wp-org-closed-plugin)
[![License](https://img.shields.io/github/license/typisttech/wp-org-closed-plugin.svg)](https://github.com/typisttech/wp-org-closed-plugin/blob/master/LICENSE)
[![Follow @TangRufus on X](https://img.shields.io/badge/Follow-TangRufus-15202B?logo=x&logoColor=white)](https://x.com/tangrufus)
[![Follow @TangRufus.com on Bluesky](https://img.shields.io/badge/Bluesky-TangRufus.com-blue?logo=bluesky)](https://bsky.app/profile/tangrufus.com)
[![Sponsor @TangRufus via GitHub](https://img.shields.io/badge/Sponsor-TangRufus-EA4AAA?logo=githubsponsors)](https://github.com/sponsors/tangrufus)
[![Hire Typist Tech](https://img.shields.io/badge/Hire-Typist%20Tech-778899)](https://typist.tech/contact/)

<p>
  <strong>Composer plugin to mark package as abandoned if closed on WordPress.org</strong>
  <br>
  <br>
  Built with ♥ by <a href="https://typist.tech/">Typist Tech</a>
</p>

</div>

---

## Usage

Once [installed](#installation), use `composer` as usual.

```console
$ composer audit
No security vulnerability advisories found.
Found 1 abandoned package:
+------------------------------------+-----------------------+
| Abandoned Package                  | Suggested Replacement |
+------------------------------------+-----------------------+
| wpackagist-plugin/my-closed-plugin | none                  |
+------------------------------------+-----------------------+
```

```console
$ composer show wpackagist-plugin/my-closed-plugin

# ...
names    : wpackagist-plugin/my-closed-plugin
Attention: This package is abandoned and no longer maintained.
# ...
```

```console
# The following commands will also show the same abandonment notice.
$ composer require
$ composer install
$ composer update

# ...
Package wpackagist-plugin/my-closed-plugin is abandoned because https://wordpress.org/plugins/my-closed-plugin has been closed, you should avoid using it. No replacement was suggested.
  - Installing wpackagist-plugin/my-closed-plugin (1.2.3): Extracting archive
# ...
```

---

> [!TIP]
> **Hire Tang Rufus!**
>
> I am looking for my next role, freelance or full-time.
> If you find this tool useful, I can build you more weird stuffs like this.
> Let's talk if you are hiring PHP / Ruby / Go developers.
>
> Contact me at https://typist.tech/contact/

---

## Why

When a plugin is closed on WordPress.org, [WPackagist](https://wpackagist.org/) not always remove it from its database immediately.
As a result, some closed plugins remain available for installation via WPackagist.

Moreover, even if a plugin is closed, its existing versions are still downloadable from WordPress.org and the subversion repository.
```json
{
  "repositories": [
    {
      "type": "package",
      "package": {
        "name": "my-plugin/my-closed-plugin",
        "version": "1.0",
        "source": {
          "type": "svn",
          "url": "https://plugins.svn.wordpress.org/my-closed-plugin/",
          "reference": "tags/1.0"
        }
      }
    },
    {
      "type": "package",
      "package": {
        "name": "your-plugin/your-closed-plugin",
        "version": "1.0",
        "dist": {
          "type": "zip",
          "url": "https://downloads.wordpress.org/plugin/your-closed-plugin.1.0.zip"
        }
      }
    }
  ]
}
```

To catch these closed plugins, `WP Org Closed Plugin` queries [WordPress.org API](https://codex.wordpress.org/WordPress.org_API#Plugins) to check whether a plugin is closed and mark them as abandoned in Composer.

## What to do when a plugin is closed?

It depends on [why the plugin is closed](https://developer.wordpress.org/plugins/wordpress-org/alerts-and-warnings/#reasons-why-plugins-are-closed).

For security concerns, stop using the plugin immediately.

For [plugin exodus](https://wptavern.com/developers-remove-plugins-from-wordpress-org-repository-after-acf-controversy), install the plugin via the new repository suggested by the plugin author.

For other reasons, do your own research.

## Caveats

### <q>No longer maintained</q>

Composer hardcodes the message <q>no longer maintained</q> for abandoned packages.

Plugins closed on WordPress.org may be [closed for various reasons](https://developer.wordpress.org/plugins/wordpress-org/alerts-and-warnings/#reasons-why-plugins-are-closed) - some are permanent, some are temporary.
The message <q>no longer maintained</q> may not be accurate in some cases.

You should check the plugin's WordPress.org page for more details.

### <q>No replacement was suggested</q>

There is no way to suggest a replacement when closing a plugin on WordPress.org.

You should do your own research to find suitable replacements.

### Locked File

Since plugin closure might be temporary, `WP Org Closed Plugin` does not modify `composer.lock`.
Thus, `$ composer audit --locked` will not report closed plugins.

```console
$ composer audit --locked

# ...
Skipped checking for closed plugins because of --locked.
# ...
```

You should run `composer audit` without `--locked` to check for closed plugins.

> [!TIP]
> **Hire Tang Rufus!**
>
> There is no need to understand any of these quirks.
> Let me handle them for you.
> I am seeking my next job, freelance or full-time.
>
> If you are hiring PHP / Ruby / Go developers,
> contact me at https://typist.tech/contact/

## Installation

```sh
composer config allow-plugins.typisttech/wp-org-closed-plugin true
composer require typisttech/wp-org-closed-plugin
```

## Credits

[`WP Org Closed Plugin`](https://github.com/typisttech/wp-org-closed-plugin) is a [Typist Tech](https://typist.tech) project and maintained by [Tang Rufus](https://x.com/TangRufus), freelance developer [for hire](https://typist.tech/contact/).

Full list of contributors can be found [on GitHub](https://github.com/typisttech/wp-org-closed-plugin/graphs/contributors).

## Copyright and License

This project is a [free software](https://www.gnu.org/philosophy/free-sw.en.html) distributed under the terms of the MIT license.
For the full license, see [LICENSE](./LICENSE).

## Contribute

Feedbacks / bug reports / pull requests are welcome.
