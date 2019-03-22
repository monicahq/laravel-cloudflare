<?php

namespace Monicahq\Cloudflare\Tests;

use Orchestra\Testbench\TestCase;
use Monicahq\Cloudflare\TrustedProxyServiceProvider;

class FeatureTestCase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            TrustedProxyServiceProvider::class,
        ];
    }

    protected function resolveApplicationCore($app)
    {
        parent::resolveApplicationCore($app);

        $app->detectEnvironment(function () {
            return 'testing';
        });
    }
}
