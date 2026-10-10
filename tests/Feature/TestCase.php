<?php

declare(strict_types=1);

namespace Tests\Feature;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Util\Loop;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function loop(): Loop
    {
        $factory = new Factory();
        return $factory->createComposer(new NullIO, null, true, null, false, true)
            ->getLoop();
    }
}
