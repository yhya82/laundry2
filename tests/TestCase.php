<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setting::get() memoizes in a static, which outlives a test's
        // database rollback -- start every test from a clean read.
        Setting::flushCache();
    }
}
