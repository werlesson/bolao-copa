<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'invite_token', 'owner_id', 'is_global', 'require_approval', 'max_members'])]
class Group extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_global'        => 'boolean',
            'require_approval' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('joined_at');
    }

    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    public function bans(): HasMany
    {
        return $this->hasMany(GroupBan::class);
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(Ranking::class);
    }
}
