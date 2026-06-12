<?php

namespace Tests\Unit;

use App\Support\MatchStage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchStageTest extends TestCase
{
    #[DataProvider('normalizeCases')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, MatchStage::normalize($input));
    }

    public static function normalizeCases(): array
    {
        return [
            'regular season alias' => ['REGULAR_SEASON', 'GROUP_STAGE'],
            'last 16 alias'        => ['LAST_16', 'ROUND_OF_16'],
            'canonical unchanged'  => ['QUARTER_FINALS', 'QUARTER_FINALS'],
            'empty defaults'       => ['', 'GROUP_STAGE'],
        ];
    }

    public function test_label_uses_normalized_stage(): void
    {
        $this->assertSame('Oitavas', MatchStage::label('LAST_16'));
        $this->assertSame('Grupos', MatchStage::label('REGULAR_SEASON'));
    }
}
