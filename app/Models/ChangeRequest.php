<?php

namespace App\Models;

use App\Enum\ChangeRequestStatus;
use App\Enum\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ChangeRequestFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'priority',
        'price',
        'paid_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => ChangeRequestStatus::class,
            'price' => 'decimal:2',
            'paid_at' => 'datetime:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (ChangeRequest $changeRequest) {
            $changeRequest->attachments->each->delete();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = "%{$term}%";

        return $query->where(function (Builder $query) use ($like) {
            $query->where('title', 'like', $like)
                ->orWhereHas('project', fn($q) => $q->where('name', 'like', $like))
                ->orWhereHas('project.company', fn($q) => $q->where('name', 'like', $like))
                ->orWhereHas('project.company.client', fn($q) => $q->where('name', 'like', $like));
        });
    }
}
