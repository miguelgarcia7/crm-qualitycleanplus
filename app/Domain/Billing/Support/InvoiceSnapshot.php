<?php

namespace App\Domain\Billing\Support;

/**
 * Reads an invoice's frozen snapshot whatever shape it was frozen in.
 *
 * Two systems froze two shapes. This app writes a flat block —
 * `address`/`city`/`state`/`zip` as sibling strings. Legacy QC Minute nested
 * them: `address: {street, city, state, zip}`. Both live in the same table
 * after the import, because a frozen document is preserved verbatim
 * (ADR-0006) rather than rewritten to suit a reader.
 *
 * Every reader goes through here, so the flat shape is an assumption made in
 * exactly one place. Echoing a nested `address` was a fatal
 * `htmlspecialchars(): argument must be of type string` in the PDF, and a
 * silently blank city/state/zip on the invoice screens.
 */
final class InvoiceSnapshot
{
    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    public static function flatten(?array $snapshot): array
    {
        $snapshot ??= [];
        $address = $snapshot['address'] ?? null;

        if (! is_array($address)) {
            return $snapshot;
        }

        // Legacy carries real values inside `address` while the sibling keys sit
        // there as nulls, so the nested value wins whenever the flat one is blank.
        return [
            ...$snapshot,
            'address' => self::pick($address, 'street'),
            'city' => self::pick($snapshot, 'city') ?? self::pick($address, 'city'),
            'state' => self::pick($snapshot, 'state') ?? self::pick($address, 'state'),
            'zip' => self::pick($snapshot, 'zip') ?? self::pick($address, 'zip'),
        ];
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private static function pick(array $from, string $key): ?string
    {
        $value = $from[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
