<?php

namespace App\Domain\People\Support;

use App\Domain\People\Models\Person;
use Carbon\CarbonInterface;

/**
 * The v1 onboarding checklist (Phase 08b-ii, people-lifecycle.md) — hardcoded
 * item set. Document items hold a polymorphic File + timestamp on `people`;
 * the I-9 additionally needs HR verification; the background check is a status.
 * HR may waive items (`onboarding_waived_items`); a waived item passes the gate.
 * Uniform issuance is tracked through inventory, not here.
 */
class OnboardingChecklist
{
    /** Document items: key => [label, file column, timestamp column]. */
    public const DOCUMENTS = [
        'id_front' => ['Government ID (front)', 'id_front_file_id', 'id_front_uploaded_at'],
        'id_back' => ['Government ID (back)', 'id_back_file_id', 'id_back_uploaded_at'],
        'i9' => ['Work authorization (I-9)', 'i9_file_id', 'i9_uploaded_at'],
        'w9' => ['W-9', 'w9_file_id', 'w9_uploaded_at'],
        'contractor_agreement' => ['Signed contractor agreement', 'contractor_agreement_file_id', 'contractor_agreement_signed_at'],
    ];

    /** All waivable item keys (documents + the background check). */
    public const ITEMS = ['id_front', 'id_back', 'i9', 'w9', 'contractor_agreement', 'background_check'];

    /**
     * Per-item status for the checklist UI.
     *
     * @return list<array{key: string, label: string, complete: bool, waived: bool, completed_at: string|null, file_id: int|null, needs_verification: bool, verified: bool, background_status: string|null}>
     */
    public static function status(Person $person): array
    {
        $waived = self::waivedItems($person);
        $items = [];

        foreach (self::DOCUMENTS as $key => [$label, $fileColumn, $timestampColumn]) {
            $fileId = $person->getAttribute($fileColumn);
            $timestamp = $person->getAttribute($timestampColumn);
            $uploaded = $fileId !== null;
            $verified = $key !== 'i9' || $person->i9_verified_at !== null;

            $items[] = [
                'key' => $key,
                'label' => $label,
                'complete' => $uploaded && $verified,
                'waived' => in_array($key, $waived, true),
                'completed_at' => $timestamp instanceof CarbonInterface ? $timestamp->toDateTimeString() : null,
                'file_id' => is_numeric($fileId) ? (int) $fileId : null,
                'needs_verification' => $key === 'i9',
                'verified' => $key === 'i9' && $person->i9_verified_at !== null,
                'background_status' => null,
            ];
        }

        $background = $person->background_check_status;
        $items[] = [
            'key' => 'background_check',
            'label' => 'Background check',
            'complete' => $background !== null && $background->satisfiesGate(),
            'waived' => in_array('background_check', $waived, true),
            'completed_at' => $person->background_check_completed_at?->toDateTimeString(),
            'file_id' => null,
            'needs_verification' => false,
            'verified' => false,
            'background_status' => $background?->value,
        ];

        return $items;
    }

    /** Whether every non-waived item passes — the promotion gate. */
    public static function isComplete(Person $person): bool
    {
        return self::missingItems($person) === [];
    }

    /**
     * Item keys still blocking promotion (incomplete and not waived).
     *
     * @return list<string>
     */
    public static function missingItems(Person $person): array
    {
        return array_values(array_map(
            fn (array $item): string => $item['key'],
            array_filter(self::status($person), fn (array $item): bool => ! $item['complete'] && ! $item['waived']),
        ));
    }

    /** @return list<string> */
    public static function waivedItems(Person $person): array
    {
        $waived = $person->onboarding_waived_items ?? [];

        return array_values(array_filter($waived, 'is_string'));
    }
}
