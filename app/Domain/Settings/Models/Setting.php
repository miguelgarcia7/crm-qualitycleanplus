<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single company-level setting. Read through {@see CompanySettings} rather
 * than directly, so callers get typed access instead of loose string keys.
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * In-request memo. Settings change rarely and are read a handful of times
     * per request; a shared cache would add invalidation across workers for no
     * measurable gain.
     *
     * @var array<string, string|null>|null
     */
    private static ?array $memo = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$memo ??= self::query()->pluck('value', 'key')->all();

        return self::$memo[$key] ?? $default;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        self::$memo = null;
    }

    /** Drop the memo — for tests and long-running processes. */
    public static function flushMemo(): void
    {
        self::$memo = null;
    }
}
