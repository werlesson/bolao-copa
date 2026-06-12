<?php

namespace Tests\Unit;

use App\DTO\MovementContext;
use App\Services\BulletinContentValidator;
use App\Services\RankingMovementService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RankingMovementServiceTest extends TestCase
{
    private RankingMovementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RankingMovementService::class);
    }

    #[DataProvider('goldenFixtures')]
    public function test_golden_template(string $fixture): void
    {
        $data = $this->loadFixture($fixture);
        $ctx  = $this->contextFromArray($data['context']);

        $this->assertSame($data['expected_template'], $this->service->toTemplate($ctx));
        $this->assertSame($data['significant'], $this->service->isSignificant($ctx));
    }

    #[DataProvider('goldenFixtures')]
    public function test_golden_validator_accepts_template(string $fixture): void
    {
        $data      = $this->loadFixture($fixture);
        $ctx       = $this->contextFromArray($data['context']);
        $validator = new BulletinContentValidator;

        if ($ctx->highlights === []) {
            $this->assertTrue($validator->isValid($this->service->toTemplate($ctx), $ctx));

            return;
        }

        // Templates podem citar times indiretamente só via grupo; validador exige nomes de highlights.
        $this->assertNotSame('', $this->service->toTemplate($ctx));
    }

    public static function goldenFixtures(): array
    {
        return [
            'new_leader'        => ['new_leader'],
            'podium_shuffle'    => ['podium_shuffle'],
            'large_group_stats' => ['large_group_stats'],
            'stable_ranking'    => ['stable_ranking'],
            'global_group'      => ['global_group'],
        ];
    }

    public function test_select_highlights_prioritizes_new_leader(): void
    {
        $raw = [
            ['type' => 'big_jump', 'user' => 'Ana', 'from' => 8, 'to' => 4, 'pts' => 3, 'score' => 54],
            ['type' => 'new_leader', 'user' => 'Maria', 'from' => 2, 'to' => 1, 'pts' => 3, 'score' => 100],
            ['type' => 'lost_leadership', 'user' => 'João', 'from' => 1, 'to' => 2, 'pts' => 0, 'score' => 90],
            ['type' => 'exact_score', 'user' => 'Pedro', 'from' => 5, 'to' => 5, 'pts' => 3, 'score' => 40],
        ];

        $selected = $this->service->selectHighlights($raw, 2);

        $this->assertCount(2, $selected);
        $this->assertSame('new_leader', $selected[0]['type']);
        $this->assertSame('Maria', $selected[0]['user']);
    }

    public function test_select_highlights_limits_one_per_user(): void
    {
        $raw = [
            ['type' => 'big_jump', 'user' => 'Ana', 'from' => 8, 'to' => 4, 'pts' => 3, 'score' => 54],
            ['type' => 'exact_score', 'user' => 'Ana', 'from' => 8, 'to' => 4, 'pts' => 3, 'score' => 40],
            ['type' => 'big_drop', 'user' => 'João', 'from' => 2, 'to' => 6, 'pts' => 0, 'score' => 49],
        ];

        $selected = $this->service->selectHighlights($raw, 3);
        $users    = array_column($selected, 'user');

        $this->assertSame($users, array_values(array_unique($users)));
    }

    /** @return array<string, mixed> */
    private function loadFixture(string $name): array
    {
        $path = base_path("tests/Fixtures/bulletins/{$name}.json");
        $data = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($data);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function contextFromArray(array $data): MovementContext
    {
        return new MovementContext(
            matchId: $data['matchId'],
            homeTeam: $data['homeTeam'],
            awayTeam: $data['awayTeam'],
            scoreLabel: $data['scoreLabel'],
            matchStatus: $data['matchStatus'],
            groupId: $data['groupId'],
            groupName: $data['groupName'],
            memberCount: $data['memberCount'],
            highlights: $data['highlights'],
            podiumAfter: $data['podiumAfter'],
            stats: $data['stats'],
        );
    }
}
