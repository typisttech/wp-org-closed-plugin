<?php

declare(strict_types=1);

namespace TypistTech\WpOrgClosedPlugin\WpOrg\UrlParser;

interface UrlParserInterface
{
    public function slug(string $url): ?string;
}
