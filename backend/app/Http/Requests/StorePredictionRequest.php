<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_id'   => ['required', 'uuid', 'exists:matches,id'],
            'home_score' => ['required', 'integer', 'min:0'],
            'away_score' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'match_id.exists'      => 'Jogo não encontrado.',
            'home_score.min'       => 'O placar não pode ser negativo.',
            'away_score.min'       => 'O placar não pode ser negativo.',
        ];
    }
}
