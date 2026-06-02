<?php

namespace Modules\GitHubApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\GitHubApp\Database\Factories\RepositoryFactory;

class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'installation_id',
        'github_repo_id',
        'full_name',
        'private',
        'default_branch',
        'review_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'private' => 'boolean',
            'review_enabled' => 'boolean',
        ];
    }

    /**
     * The installation this repository belongs to.
     *
     * @return BelongsTo<Installation, $this>
     */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    /**
     * The pull requests ingested for this repository.
     *
     * @return HasMany<PullRequest, $this>
     */
    public function pullRequests(): HasMany
    {
        return $this->hasMany(PullRequest::class);
    }
}
