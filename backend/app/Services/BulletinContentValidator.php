<?php

namespace App\Services;

use App\DTO\MovementContext;

class BulletinContentValidator
{
    private const MAX_LENGTH = 220;

    private const MAX_SENTENCES = 3;

    /** @var list<string> */
    private const PROTECTED_ABBREVIATIONS = [
        'Prof', 'Dr', 'Mr', 'Mrs', 'Ms', 'Sr', 'Sra', 'Dra', 'Jr', 'PhD', 'MSc', 'BSc', 'Eng', 'Av', 'Des',
    ];

    public function isValid(string $content, MovementContext $ctx): bool
    {
        $content = trim($content);

        if (strlen($content) < 20 || strlen($content) > self::MAX_LENGTH) {
            return false;
        }

        if ($this->sentenceCount($content) > self::MAX_SENTENCES) {
            return false;
        }

        if (! $this->mentionsRankingMovement($content, $ctx)) {
            return false;
        }

        if (! $this->avoidsScoreAndTeams($content, $ctx)) {
            return false;
        }

        if (! $this->highlightNamesAreAllowed($content, $ctx)) {
            return false;
        }

        if (! $this->positionClaimsAreValid($content, $ctx)) {
            return false;
        }

        return true;
    }

    private function mentionsRankingMovement(string $content, MovementContext $ctx): bool
    {
        foreach ($ctx->allowedNames() as $name) {
            if (stripos($content, $name) !== false) {
                return true;
            }
        }

        return stripos($content, $ctx->groupName) !== false;
    }

    private function avoidsScoreAndTeams(string $content, MovementContext $ctx): bool
    {
        if ($ctx->scoreLabel !== '×' && str_contains($content, $ctx->scoreLabel)) {
            return false;
        }

        if (preg_match('/\d+\s*[×xX]\s*\d+/u', $content)) {
            return false;
        }

        if (stripos($content, $ctx->homeTeam) !== false) {
            return false;
        }

        return stripos($content, $ctx->awayTeam) === false;
    }

    private function highlightNamesAreAllowed(string $content, MovementContext $ctx): bool
    {
        $allowed = $ctx->allowedNames();

        foreach ($allowed as $name) {
            $parts = preg_split('/\s+/', $name) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $surname = $parts[count($parts) - 1];
            if (strlen($surname) >= 4 && stripos($content, $surname) !== false) {
                if (stripos($content, $name) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    private function positionClaimsAreValid(string $content, MovementContext $ctx): bool
    {
        $allowed = [];

        foreach ($ctx->highlights as $highlight) {
            if ($highlight['from'] !== null) {
                $allowed[] = (string) $highlight['from'];
            }
            $allowed[] = (string) $highlight['to'];
        }

        foreach ($ctx->podiumAfter as $entry) {
            $allowed[] = (string) $entry['pos'];
        }

        $allowed = array_unique($allowed);

        if (! preg_match_all('/(\d+)º/u', $content, $matches)) {
            return true;
        }

        foreach ($matches[1] as $position) {
            if (! in_array($position, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function sentenceCount(string $content): int
    {
        $protected = $content;

        foreach (self::PROTECTED_ABBREVIATIONS as $abbrev) {
            $protected = preg_replace(
                '/\b'.preg_quote($abbrev, '/').'\./iu',
                $abbrev.'⸴',
                $protected,
            ) ?? $protected;
        }

        $parts = preg_split('/[!?]+|\.\s+/u', $protected) ?: [];

        return count(array_filter(array_map('trim', $parts)));
    }
}
