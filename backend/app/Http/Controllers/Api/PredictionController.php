<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePredictionRequest;
use App\Http\Resources\PredictionResource;
use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PredictionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $predictions = $request->user()
            ->predictions()
            ->with('match')
            ->latest()
            ->get();

        return PredictionResource::collection($predictions);
    }

    public function store(StorePredictionRequest $request): JsonResponse
    {
        // FormRequest already validates exists:matches,id — find() is safe here.
        $match = FootballMatch::find($request->validated('match_id'));

        if ($match->starts_at->lte(now())) {
            return response()->json([
                'message' => 'O prazo para palpites neste jogo já encerrou.',
            ], 422);
        }

        $prediction = Prediction::updateOrCreate(
            [
                'user_id'  => $request->user()->id,
                'match_id' => $match->id,
            ],
            [
                'home_score' => $request->validated('home_score'),
                'away_score' => $request->validated('away_score'),
            ]
        );

        return (new PredictionResource($prediction))
            ->response()
            ->setStatusCode($prediction->wasRecentlyCreated ? 201 : 200);
    }
}
