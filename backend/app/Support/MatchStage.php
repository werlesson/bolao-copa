<?php

namespace App\Support;

class MatchStage
{
    /** @var array<string, string> */
    private const ALIASES = [
        'REGULAR_SEASON' => 'GROUP_STAGE',
        'LAST_16'        => 'ROUND_OF_16',
    ];

    public const PHASE_ORDER = [
        'GROUP_STAGE',
        'LAST_32',
        'ROUND_OF_16',
        'QUARTER_FINALS',
        'SEMI_FINALS',
        'THIRD_PLACE',
        'FINAL',
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'GROUP_STAGE'    => 'Grupos',
        'LAST_32'        => 'Rodada 32',
        'ROUND_OF_16'    => 'Oitavas',
        'QUARTER_FINALS' => 'Quartas',
        'SEMI_FINALS'    => 'Semi-final',
        'THIRD_PLACE'    => '3º Lugar',
        'FINAL'          => 'Final',
    ];

    public static function normalize(?string $stage): string
    {
        if ($stage === null || $stage === '') {
            return 'GROUP_STAGE';
        }

        return self::ALIASES[$stage] ?? $stage;
    }

    public static function label(string $stage): string
    {
        $normalized = self::normalize($stage);

        return self::LABELS[$normalized] ?? $stage;
    }
}
