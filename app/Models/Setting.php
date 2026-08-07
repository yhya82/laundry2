<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    /**
     * Every page reads several of these (Terminal alone touches half a
     * dozen), and each was a separate `where key = ?` query -- get() is
     * called ~30 places across the app, so an unmemoized version means
     * dozens of avoidable round-trips on a single request. One query loads
     * every row up front instead; still one real query per request (the
     * cache doesn't survive past it), just not one per call.
     *
     * @var Collection<string, static>|null
     */
    protected static ?Collection $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        static::$cache ??= static::all()->keyBy('key');

        return static::$cache->get($key)?->value ?? $default;
    }

    public static function set(string $key, ?string $value, string $group, string $type = 'string'): void
    {
        static::updateOrCreate(['key' => $key], ['group' => $group, 'value' => $value, 'type' => $type]);

        static::$cache = null;
    }
}
