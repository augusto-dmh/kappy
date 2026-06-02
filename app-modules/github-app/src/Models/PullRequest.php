<?php

namespace Modules\GitHubApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\GitHubApp\Database\Factories\PullRequestFactory;
use Modules\GitHubApp\Enums\PullRequestState;

class PullRequest extends Model
{
    /** @use HasFactory<PullRequestFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'github_pr_number',
        'title',
        'author_login',
        'base_sha',
        'head_sha',
        'state',
        'linked_issue_ref',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PullRequestState::class,
        ];
    }

    /**
     * The repository this pull request belongs to.
     *
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
