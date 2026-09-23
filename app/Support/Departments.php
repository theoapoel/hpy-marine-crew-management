<?php

namespace App\Support;

/**
 * The shipboard department a rank belongs to — the grouping the dashboard charts
 * colour by. Read off the rank's name, so a rank added in ERP HPY lands somewhere
 * sensible without a list to maintain here.
 */
class Departments
{
    /**
     * Chart colour per department, in fixed order. Validated as a categorical palette
     * (lightness, chroma, colour-blind separation, contrast) — change them as a set.
     */
    public const COLORS = [
        'Deck' => '#1a73e8',
        'Engine' => '#e8710a',
        'Catering' => '#9334e6',
        'Other' => '#9aa0a6',
    ];

    public static function of(?string $rank): string
    {
        $rank = mb_strtolower(trim((string) $rank));

        return match (true) {
            $rank === '', $rank === 'unspecified' => 'Other',
            (bool) preg_match('/engineer|oiler|wiper|fitter|motorman|electric|\beto\b|engine|pump/', $rank) => 'Engine',
            (bool) preg_match('/cook|steward|mess|chef|catering|galley/', $rank) => 'Catering',
            default => 'Deck',
        };
    }

    public static function color(string $department): string
    {
        return self::COLORS[$department] ?? self::COLORS['Other'];
    }
}
