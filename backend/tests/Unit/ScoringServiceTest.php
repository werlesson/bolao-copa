<?php

namespace Tests\Unit;

use App\Services\ScoringService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new ScoringService();
    }

    #[DataProvider('scoreCases')]
    public function test_calculate(int $predHome, int $predAway, int $realHome, int $realAway, int $expected): void
    {
        $this->assertSame(
            $expected,
            $this->scoring->calculate($predHome, $predAway, $realHome, $realAway),
        );
    }

    public static function scoreCases(): array
    {
        return [
            'exact score'        => [2, 1, 2, 1, 3],
            'correct home win'   => [1, 0, 3, 0, 1],
            'correct away win'   => [0, 2, 0, 1, 1],
            'correct draw'       => [1, 1, 0, 0, 1],
            'wrong winner'       => [2, 0, 0, 1, 0],
            'wrong draw'         => [1, 1, 2, 0, 0],
            'zero zero exact'    => [0, 0, 0, 0, 3],
            'high scoring exact' => [4, 3, 4, 3, 3],
        ];
    }
}
