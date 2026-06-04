<?php

namespace Modules\GitHubApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\GitHubApp\Database\Factories\InstallationFactory;
use Modules\GitHubApp\Enums\InstallationTarget;
use Modules\GitHubApp\Enums\RepositorySelection;
use Modules\Identity\Models\Account;

class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'github_installation_id',
        'target_type',
        'suspended_at',
        'repositories_selection',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => InstallationTarget::class,
            'repositories_selection' => RepositorySelection::class,
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * The account this installation belongs to.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The repositories covered by this installation.
     *
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    /**
     * The webhook deliveries recorded against this installation.
     *
     * @return HasMany<WebhookEvent, $this>
     */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }
}
