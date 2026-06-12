<?php

namespace App\Services;

class ScoringService
{
    public const MAX_POINTS_PER_MATCH = 3;

    public function calculate(int $predHome, int $predAway, int $realHome, int $realAway): int
    {
        if ($predHome === $realHome && $predAway === $realAway) {
            return self::MAX_POINTS_PER_MATCH;
        }

        if ($this->getResult($predHome, $predAway) === $this->getResult($realHome, $realAway)) {
            return 1;
        }

        return 0;
    }

    private function getResult(int $home, int $away): string
    {
        if ($home > $away) return 'home';
        if ($away > $home) return 'away';
        return 'draw';
    }
}
