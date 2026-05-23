<?php

namespace App\Models;

use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[ObservedBy([UserObserver::class])]
#[Fillable(['name', 'email', 'avatar_url', 'google_id', 'push_subscription', 'onboarding_done', 'is_admin'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected function casts(): array
    {
        return [
            'push_subscription' => 'array',
            'onboarding_done'   => 'boolean',
            'is_admin'          => 'boolean',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('joined_at');
    }

    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(Ranking::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    public function bans(): HasMany
    {
        return $this->hasMany(GroupBan::class);
    }
}
