<?php

namespace Tests\Unit;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setting::$cache is a static property -- RefreshDatabase rolls
        // back the DB between tests but has no idea this in-memory cache
        // exists, so without this a later test can silently see settings
        // "set" by an earlier, already-rolled-back test.
        $reflection = new \ReflectionProperty(Setting::class, 'cache');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);
    }

    public function test_get_returns_the_default_when_unset(): void
    {
        $this->assertNull(Setting::get('does.not.exist'));
        $this->assertSame('fallback', Setting::get('does.not.exist', 'fallback'));
    }

    public function test_get_and_set_round_trip(): void
    {
        Setting::set('test.key', 'hello', 'test');

        $this->assertSame('hello', Setting::get('test.key'));
    }

    public function test_get_loads_all_settings_in_a_single_query_then_serves_subsequent_calls_from_memory(): void
    {
        Setting::set('test.a', '1', 'test');
        Setting::set('test.b', '2', 'test');
        Setting::set('test.c', '3', 'test');

        DB::enableQueryLog();

        Setting::get('test.a');
        Setting::get('test.b');
        Setting::get('test.c');
        Setting::get('test.does.not.exist');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries, 'Four get() calls should have produced exactly one query, not four.');
    }

    public function test_set_invalidates_the_cache_so_a_later_get_sees_the_new_value(): void
    {
        Setting::set('test.key', 'first', 'test');
        $this->assertSame('first', Setting::get('test.key'));

        Setting::set('test.key', 'second', 'test');

        $this->assertSame('second', Setting::get('test.key'), 'A later get() must not still be serving the stale cached value.');
    }

    public function test_set_invalidation_is_visible_even_with_the_cache_already_warm(): void
    {
        Setting::set('test.key', 'first', 'test');
        Setting::get('test.key'); // warm the cache

        DB::enableQueryLog();
        Setting::set('test.key', 'second', 'test');
        $value = Setting::get('test.key');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('second', $value);
        // set() writes the row, then get() must re-query since the cache
        // was cleared -- not silently keep serving the pre-set() snapshot.
        $this->assertGreaterThanOrEqual(2, count($queries), 'Expected at least the write plus a fresh reload after invalidation.');
    }
}
