<?php

namespace App\Domain\Settings\Support;

use App\Domain\Settings\Models\Setting;

/**
 * QCP's own identity — the "From" block frozen onto every invoice at generation
 * (ADR-0006). Stored in the database so an office manager can correct it
 * without a deploy; `config/qcp.php` remains the seed default for a fresh
 * install, never the live source.
 */
class CompanySettings
{
    /** Setting key → the config path its default comes from. */
    public const INVOICER_FIELDS = [
        'invoicer.name' => 'qcp.invoicer.name',
        'invoicer.address' => 'qcp.invoicer.address',
        'invoicer.city' => 'qcp.invoicer.city',
        'invoicer.state' => 'qcp.invoicer.state',
        'invoicer.zip' => 'qcp.invoicer.zip',
        'invoicer.phone' => 'qcp.invoicer.phone',
        'invoicer.email' => 'qcp.invoicer.email',
    ];

    /**
     * The invoicer block, shaped exactly as `config('qcp.invoicer')` was — the
     * snapshot written onto invoices, so the stored shape does not change.
     *
     * @return array<string, string>
     */
    public function invoicer(): array
    {
        $invoicer = [];

        foreach (self::INVOICER_FIELDS as $key => $configPath) {
            $field = str_replace('invoicer.', '', $key);
            $invoicer[$field] = Setting::get($key) ?? (string) config($configPath, '');
        }

        return $invoicer;
    }

    /**
     * @param  array<string, string|null>  $invoicer  field => value
     */
    public function updateInvoicer(array $invoicer): void
    {
        $values = [];

        foreach (array_keys(self::INVOICER_FIELDS) as $key) {
            $field = str_replace('invoicer.', '', $key);

            if (array_key_exists($field, $invoicer)) {
                $values[$key] = $invoicer[$field] !== null ? trim($invoicer[$field]) : null;
            }
        }

        Setting::put($values);
    }

    /**
     * Fields left blank that will appear blank on every invoice generated from
     * now on — and permanently, since the snapshot freezes. Surfaced on the
     * settings screen so nobody discovers it from a client's copy.
     *
     * @return list<string>
     */
    public function missingInvoicerFields(): array
    {
        return collect($this->invoicer())
            ->filter(fn (string $value): bool => trim($value) === '')
            ->keys()
            ->values()
            ->all();
    }
}
