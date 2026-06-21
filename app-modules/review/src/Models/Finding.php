<?php

namespace Modules\Review\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Review\Database\Factories\FindingFactory;
use Modules\Review\Enums\CriticVerdict;
use Modules\Review\Enums\FindingCategory;
use Modules\Review\Enums\FindingSeverity;
use Modules\Review\Enums\FindingStatus;

class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'review_id',
        'category',
        'severity',
        'path',
        'line',
        'title',
        'message',
        'suggestion',
        'agent_prompt',
        'confidence',
        'critic_verdict',
        'critic_reason',
        'status',
        'github_comment_id',
        'fingerprint',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FindingCategory::class,
            'severity' => FindingSeverity::class,
            'critic_verdict' => CriticVerdict::class,
            'status' => FindingStatus::class,
        ];
    }

    /**
     * The review this finding belongs to.
     *
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
