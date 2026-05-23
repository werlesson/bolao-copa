<?php

namespace App\Services;

class ScoringService
{
    public function calculate(int $predHome, int $predAway, int $realHome, int $realAway): int
    {
        if ($predHome === $realHome && $predAway === $realAway) {
            return 3;
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
