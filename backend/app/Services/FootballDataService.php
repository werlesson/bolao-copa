<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FootballDataService
{
    private const BASE_URL = 'https://api.football-data.org/v4';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.football_data.api_key');
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchMatches(): array
    {
        $competition = config('services.football_data.competition', 'WC');

        $response = Http::withHeader('X-Auth-Token', $this->apiKey)
            ->timeout(30)
            ->retry(2, 500)
            ->get(self::BASE_URL . "/competitions/{$competition}/matches");

        $response->throw();

        return $response->json('matches', []);
    }
}
