<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * ARM THE SAME ELOQUENT STRICTNESS THE DEV ENVIRONMENT USES.
         *
         * `AppServiceProvider` turns these on only when `app()->isLocal()`, and PHPUnit runs
         * with APP_ENV=testing — so the suite has been running with the guard OFF while the
         * dev server has it ON. That asymmetry is why a lazy-loading crash in the shop's API
         * resource had to be found by hand through tinker rather than by the test that covers
         * the endpoint: the test passed in an environment where the bug cannot occur.
         *
         * Enabling it here means an N+1 or a silently-discarded attribute fails a test rather
         * than a request in front of a user.
         */
        Model::preventLazyLoading(true);
        Model::preventSilentlyDiscardingAttributes(true);
    }
}
