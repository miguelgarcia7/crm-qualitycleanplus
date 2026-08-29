<?php

namespace Database\Seeders;

use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Support\CompanySettings;
use Illuminate\Database\Seeder;

/**
 * Seeds company identity from `config/qcp.php` — which reads the QCP_INVOICER_*
 * environment variables. That makes the environment the *initial* source on a
 * fresh install; once seeded, the database is authoritative and the screen at
 * /admin/settings/company is how it changes.
 *
 * Idempotent, and never overwrites a value someone has already edited.
 */
class CompanySettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CompanySettings::INVOICER_FIELDS as $key => $configPath) {
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }

            Setting::query()->create([
                'key' => $key,
                'value' => (string) config($configPath, ''),
            ]);
        }

        Setting::flushMemo();
    }
}
