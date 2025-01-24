<?php

declare(strict_types=1);

namespace TypistTech\WpSecAdvi\WpOrgClosedPlugin\WPOrg\UrlParser;

interface UrlParserInterface
{
    public function slug(string $url): ?string;
}
