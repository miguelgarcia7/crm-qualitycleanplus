<?php

namespace App\Domain\LegacyImport\Support;

/**
 * Duplicate legacy users, resolved (phase-final-cutover.md "Duplicate people").
 *
 * The legacy app let the same person register more than once (26 phone numbers
 * shared across 62 user rows, dump of 2026-08-30). Rather than editing legacy
 * production data, the importer folds each group into one survivor: the loser
 * ids map to the survivor's new person id in legacy_id_map, so every later step
 * (work orders, time entries, deductions, comments) repoints automatically.
 *
 * Survivors were chosen by punch history and account state in the planning
 * session; losers contribute their email/phone only where the survivor lacks one.
 */
class PeopleMergeMap
{
    /** Legacy user ids that are pure test rows — not imported at all. */
    public const DROP = [140, 141, 142, 192];

    /** @var array<int, list<int>> survivor legacy id => merged-away legacy ids */
    public const MERGE = [
        150 => [75, 130, 146],   // Victoria Segovia
        169 => [118],            // Rosa Salas
        171 => [181, 112],       // Saul Cartagena
        125 => [144],            // Silmary Torres
        175 => [89, 91],         // Abigail Vasquez
        172 => [68, 80],         // Itzel Rodriguez
        61 => [184, 189],        // Carolina Perez
        79 => [124],             // Maribel Galvan
        156 => [69],             // Argenis Bravo
        134 => [157],            // Ignacio Quintero
        1364 => [1313],          // Ana Chavez
        92 => [151, 162],        // Regina Salazar
        170 => [123],            // Ashly Ruiz
        155 => [66],             // Jeremy Bravo
        179 => [88],             // Jazmin Olivas
        195 => [126],            // Juan Molina
        145 => [143],            // Anthony Malagon
        133 => [64],             // Anderson Mora
        208 => [122],            // Maria Aguilar
        206 => [48, 207],        // Luz Vargas
        154 => [131],            // Imalay Rojas
        153 => [174],            // Antonio Noguera
        116 => [152],            // Julio Perez
    ];

    /** @return array<int, int> loser legacy id => survivor legacy id */
    public static function losers(): array
    {
        $map = [];

        foreach (self::MERGE as $survivor => $merged) {
            foreach ($merged as $loser) {
                $map[$loser] = $survivor;
            }
        }

        return $map;
    }
}
