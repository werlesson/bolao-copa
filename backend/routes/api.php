<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ─── Auth ────────────────────────────────────────────────────────────────────
Route::prefix('auth/google')->middleware('throttle:15,1')->group(function () {
    Route::get('redirect', [AuthController::class, 'redirect']);
    Route::get('callback', [AuthController::class, 'callback']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// ─── Autenticado ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    // ─── User profile — read ──────────────────────────────────────────────
    Route::get('user', [UserController::class, 'show']);

    // ─── Matches ─────────────────────────────────────────────────────────────
    Route::get('matches', [MatchController::class, 'index']);
    Route::get('matches/{match}', [MatchController::class, 'show']);

    // ─── Predictions — read ───────────────────────────────────────────────────
    Route::get('predictions', [PredictionController::class, 'index']);

    // ─── Groups — literal routes first to avoid {group} swallowing "join" ────
    Route::get('groups/invite/{token}', [GroupController::class, 'invitePreview']);
    Route::get('groups', [GroupController::class, 'index']);
    Route::get('groups/{group}', [GroupController::class, 'show']);
    Route::get('groups/{group}/requests', [GroupController::class, 'requests']);

    // ─── Rankings ────────────────────────────────────────────────────────────
    Route::get('groups/{group}/ranking', [RankingController::class, 'groupRanking']);
    Route::get('rankings/global', [RankingController::class, 'globalRanking']);

    // ─── Write endpoints — stricter rate limit (20 req/min) ──────────────────
    Route::middleware('throttle:20,1')->group(function () {
        // User profile — writes
        Route::patch('user', [UserController::class, 'update']);
        Route::patch('user/onboarding', [UserController::class, 'completeOnboarding']);
        Route::post('user/onboarding', [UserController::class, 'completeOnboarding']);
        Route::put('user/push-subscription', [UserController::class, 'storePushSubscription']);
        Route::delete('user/push-subscription', [UserController::class, 'destroyPushSubscription']);

        // Predictions — write
        Route::post('predictions', [PredictionController::class, 'store']);

        // Groups — writes
        Route::post('groups/join/{token}', [GroupController::class, 'join']);
        Route::post('groups', [GroupController::class, 'store']);
        Route::patch('groups/{group}', [GroupController::class, 'update']);
        Route::delete('groups/{group}', [GroupController::class, 'destroy']);
        Route::post('groups/{group}/leave', [GroupController::class, 'leave']);
        Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);
        Route::patch('groups/{group}/transfer', [GroupController::class, 'transfer']);
        Route::post('groups/{group}/invite/regenerate', [GroupController::class, 'regenerateInvite']);
        Route::post('groups/{group}/requests/{joinRequest}/approve', [GroupController::class, 'approveRequest']);
        Route::post('groups/{group}/requests/{joinRequest}/reject', [GroupController::class, 'rejectRequest']);
        Route::delete('groups/{group}/requests/{joinRequest}', [GroupController::class, 'cancelRequest']);
    });

    // ─── Admin ───────────────────────────────────────────────────────────────
    Route::middleware(['admin', 'throttle:20,1'])->prefix('admin')->group(function () {
        Route::get('matches', [AdminController::class, 'matches']);
        Route::post('matches/sync', [AdminController::class, 'syncMatches']);
        Route::get('users', [AdminController::class, 'users']);
    });
});
