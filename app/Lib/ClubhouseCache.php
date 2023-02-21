<?php

namespace App\Lib;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class ClubhouseCache
{

    /**
     * Retrieve the cache store -- use Octane for production, and the default for local (database) & testing (array).
     *
     * @return Repository
     */

    public static function store(): Repository
    {
        return Cache::store(app()->isProduction() ? 'octane' : config('cache.default'));
    }

    /**
     * Put a cache entry
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */

    public static function put(string $key, mixed $value): void
    {
        self::store()->put($key, $value);
    }

    /**
     * Get a cache entry
     *
     * @param string $key
     * @return mixed
     * @throws InvalidArgumentException
     */

    public static function get(string $key): mixed
    {
        return self::store()->get($key);
    }

    /**
     * Does the cache have a key?
     *
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */

    public static function has(string $key): bool
    {
        return self::store()->has($key);
    }

    /**
     * Delete a cache entry
     *
     * @param string $key
     * @return void
     */

    public static function forget(string $key): void
    {
        self::store()->forget($key);
    }

    /**
     * Flush/dump the entire cache
     *
     * @return void
     */

    public static function flush(): void
    {
        self::store()->flush();
    }


}