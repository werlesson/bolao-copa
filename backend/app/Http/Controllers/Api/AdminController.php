<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Jobs\SyncMatchResults;
use App\Models\FootballMatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminController extends Controller
{
    public function matches(): AnonymousResourceCollection
    {
        $matches = FootballMatch::orderBy('starts_at')->get();

        return MatchResource::collection($matches);
    }

    public function syncMatches(): JsonResponse
    {
        SyncMatchResults::dispatch();

        return response()->json(['message' => 'Sincronização disparada.']);
    }

    public function users(): JsonResponse
    {
        $users = User::withCount('predictions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'                => $u->id,
                'name'              => $u->name,
                'email'             => $u->email,
                'avatar_url'        => $u->avatar_url,
                'is_admin'          => $u->is_admin,
                'onboarding_done'   => $u->onboarding_done,
                'predictions_count' => $u->predictions_count,
            ]);

        return response()->json(['data' => $users]);
    }
}
