<?php

namespace Tests\Unit;

use App\DTO\MovementContext;
use App\Services\BulletinContentValidator;
use Tests\TestCase;

class BulletinContentValidatorTest extends TestCase
{
    private BulletinContentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BulletinContentValidator;
    }

    public function test_accepts_valid_ai_message_without_score(): void
    {
        $ctx = $this->context();

        $content = 'Maria passou na frente e agora manda no Trabalho! João perdeu a ponta.';

        $this->assertTrue($this->validator->isValid($content, $ctx));
    }

    public function test_accepts_name_with_title_abbreviation(): void
    {
        $ctx = new MovementContext(
            matchId: 'm1',
            homeTeam: 'Canada',
            awayTeam: 'Bosnia-H.',
            scoreLabel: '1×1',
            matchStatus: 'FINISHED',
            groupId: 'g1',
            groupName: 'Resenha do Zap',
            memberCount: 20,
            highlights: [
                ['type' => 'new_leader', 'user' => 'Prof. Sheila Dibbert', 'from' => 2, 'to' => 1, 'pts' => 3],
            ],
            podiumAfter: [
                ['user' => 'Prof. Sheila Dibbert', 'pos' => 1, 'pts' => 10],
            ],
            stats: ['scored' => 5, 'moved' => 3, 'exact_hits' => 1],
        );

        $content = 'Prof. Sheila Dibbert passou na frente no Resenha do Zap!';

        $this->assertTrue($this->validator->isValid($content, $ctx));
    }

    public function test_rejects_message_without_ranking_context(): void
    {
        $ctx = $this->context();

        $this->assertFalse($this->validator->isValid(
            'Alguém pontuou bastante e o ranking geral mexeu.',
            $ctx,
        ));
    }

    public function test_rejects_score_in_message(): void
    {
        $ctx = $this->context();

        $this->assertFalse($this->validator->isValid(
            'Brasil 2×0 Chile: Maria passou na frente no Trabalho!',
            $ctx,
        ));
    }

    public function test_rejects_team_names_in_message(): void
    {
        $ctx = $this->context();

        $this->assertFalse($this->validator->isValid(
            'Depois de Brasil x Chile, Maria passou na frente no Trabalho!',
            $ctx,
        ));
    }

    public function test_rejects_invented_position(): void
    {
        $ctx = $this->context();

        $this->assertFalse($this->validator->isValid(
            'Maria subiu do 10º pro 1º no Trabalho!',
            $ctx,
        ));
    }

    public function test_rejects_too_many_sentences(): void
    {
        $ctx = $this->context();

        $content = 'Maria passou na frente. João caiu. Pedro pontuou. Ana também.';

        $this->assertFalse($this->validator->isValid($content, $ctx));
    }

    /** @return MovementContext */
    private function context(): MovementContext
    {
        return new MovementContext(
            matchId: 'm1',
            homeTeam: 'Brasil',
            awayTeam: 'Chile',
            scoreLabel: '2×0',
            matchStatus: 'FINISHED',
            groupId: 'g1',
            groupName: 'Trabalho',
            memberCount: 10,
            highlights: [
                ['type' => 'new_leader', 'user' => 'Maria', 'from' => 3, 'to' => 1, 'pts' => 3],
                ['type' => 'lost_leadership', 'user' => 'João', 'from' => 1, 'to' => 2, 'pts' => 0],
            ],
            podiumAfter: [
                ['user' => 'Maria', 'pos' => 1, 'pts' => 12],
                ['user' => 'João', 'pos' => 2, 'pts' => 11],
            ],
            stats: ['scored' => 4, 'moved' => 2, 'exact_hits' => 1],
        );
    }
}
