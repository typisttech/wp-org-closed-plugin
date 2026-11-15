<?php

declare(strict_types=1);

namespace Tests\Feature\WpOrg\Api;

use TypistTech\WpOrgClosedPlugin\WpOrg\Api\ArrayCache;
use TypistTech\WpOrgClosedPlugin\WpOrg\Api\Client;

covers(Client::class);

it('checks the live WordPress.org API', function (string $slug, bool $expected): void {
    $loop = $this->loop();
    $client = new Client($loop->getHttpDownloader(), $loop, new ArrayCache);

    expect($client->isClosed($slug))->toBe($expected);
})->with([
    'closed' => ['spam-stopgap', true],
    'open' => ['hello-dolly', false],
])->group('live');
