<?php

namespace Monicahq\Cloudflare\Tests\Unit\Commands;

use Illuminate\Support\Facades\Cache;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

class ViewTest extends FeatureTestCase
{
    #[Test]
    public function it_displays_addresses()
    {
        Cache::forever('cloudflare.proxies', ['expect']);

        $this->artisan('cloudflare:view')
            ->expectsTable(['Address'], [['expect']])
            ->assertExitCode(0);
    }
}
