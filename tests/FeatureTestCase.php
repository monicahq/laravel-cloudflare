<?php

namespace Monicahq\Cloudflare\Tests;

use Monicahq\Cloudflare\TrustedProxyServiceProvider;
use Orchestra\Testbench\TestCase;

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
