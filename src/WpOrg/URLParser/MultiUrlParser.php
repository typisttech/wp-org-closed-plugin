<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin\WpOrg\UrlParser;

readonly class MultiUrlParser implements UrlParserInterface
{
    /** @var UrlParserInterface[] */
    private array $urlParsers;

    public function __construct(
        UrlParserInterface ...$urlParsers,
    ) {
        $this->urlParsers = $urlParsers;
    }

    public function slug(string $url): ?string
    {
        foreach ($this->urlParsers as $urlParser) {
            $slug = $urlParser->slug($url);

            if ($slug !== null) {
                return $slug;
            }
        }

        return null;
    }
}
