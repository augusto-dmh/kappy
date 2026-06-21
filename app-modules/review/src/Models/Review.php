<?php

namespace Modules\Review\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\GitHubApp\Models\PullRequest;
use Modules\Review\Database\Factories\ReviewFactory;
use Modules\Review\Enums\ReviewStatus;
use Modules\Review\Enums\ReviewTrigger;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pull_request_id',
        'head_sha',
        'trigger',
        'status',
        'is_incremental',
        'generator_model',
        'critic_model',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'cost_cents',
        'github_check_run_id',
        'summary_comment_id',
        'started_at',
        'finished_at',
        'failure_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => ReviewTrigger::class,
            'status' => ReviewStatus::class,
            'is_incremental' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The pull request this review was run against.
     *
     * @return BelongsTo<PullRequest, $this>
     */
    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * The findings produced by this review.
     *
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
