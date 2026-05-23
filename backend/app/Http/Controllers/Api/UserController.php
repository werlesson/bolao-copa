<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePushSubscriptionRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateUserRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user());
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $request->user()->update(['onboarding_done' => true]);

        return response()->json(['message' => 'Onboarding concluído.']);
    }

    public function storePushSubscription(UpdatePushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->update([
            'push_subscription' => $request->validated(),
        ]);

        return response()->json(['message' => 'Subscription salva.'], 201);
    }

    public function destroyPushSubscription(Request $request): JsonResponse
    {
        $request->user()->update(['push_subscription' => null]);

        return response()->json(['message' => 'Subscription removida.']);
    }
}
