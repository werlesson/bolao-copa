<?php

namespace App\Services;

use App\Contracts\RankingBulletinGenerator;
use App\Exceptions\BulletinGenerationException;
use Illuminate\Support\Facades\Http;

class GeminiBulletinGenerator implements RankingBulletinGenerator
{
    /** @throws BulletinGenerationException */
    public function generate(array $payload): string
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            throw new BulletinGenerationException('GEMINI_API_KEY não configurada.');
        }

        $model = config('services.gemini.model');
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeader('x-goog-api-key', $apiKey)
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $this->systemPrompt()]],
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => config('services.gemini.max_output_tokens'),
                    'temperature'     => config('services.gemini.temperature'),
                ],
            ]);

        if (! $response->successful()) {
            throw new BulletinGenerationException(
                'Gemini respondeu com HTTP '.$response->status().'.',
            );
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new BulletinGenerationException('Gemini retornou resposta vazia.');
        }

        return trim($text);
    }

    private function systemPrompt(): string
    {
        $version = config('services.gemini.prompt_version');

        return <<<PROMPT
Você comenta o bolão no grupo do WhatsApp — tom de resenha entre amigos, descontraído e divertido, como quem manda áudio depois do jogo.

Estilo (prompt v{$version}):
- Fale como torcedor/palpiteiro, NÃO como jornalista ou narrador de TV.
- NÃO inclua placar (ex.: 2×0, 1x1) nem nomes dos times — o app já mostra o jogo no card.
- Comente só a movimentação no ranking: quem subiu, caiu, virou líder, entrou no pódio.
- Pode usar gírias leves: cravou, despencou, voou, invadiu o pódio, passou na frente, tomou a ponta, saiu na frente.
- Pode usar expressões coloquiais: "eita", "olha só", "que loucura", "tá pegado", "mexeu geral".
- Máximo 2 frases curtas em português do Brasil.
- Use SOMENTE os dados do JSON (nomes, posições, stats).
- Mencione no máximo 2 pessoas pelo nome; priorize os highlights.
- Use stats para resumir o restante sem listar nomes.
- No máximo 1 emoji opcional (🏆 😅 🔥 — sem exagero).
- Não invente fatos, rivalidades ou estatísticas extras.

Exemplos de tom (não copie literalmente):
- "Eita, a Maria passou na frente — agora ela manda no Bolão da Firma! 🔥"
- "O João cravou e saiu voando pro 3º. Tá animado esse bolão."
- "Mexeu geral: 12 pontuaram e quase ninguém ficou parado."
PROMPT;
    }
}
