<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, SoftDeletes;

    public const FOLLOW_UP_ALERT_DAYS = 360;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'last_follow_up',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_follow_up' => 'date:Y-m-d'
        ];
    }

    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('last_follow_up')
                ->orWhere('last_follow_up', '<', now()->subDays(self::FOLLOW_UP_ALERT_DAYS)->toDateString());
        });
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function projects(): HasManyThrough
    {
        return $this->hasManyThrough(Project::class, Company::class);
    }
}
