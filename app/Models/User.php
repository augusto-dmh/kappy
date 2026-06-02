<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Identity\Enums\AccountType;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;

#[Fillable(['name', 'email', 'password', 'github_id', 'github_login', 'avatar_url', 'github_token'])]
#[Hidden(['password', 'github_token', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'github_token' => 'encrypted',
        ];
    }

    /**
     * The accounts this user belongs to, with their membership role.
     *
     * @return BelongsToMany<Account, $this, Membership>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'memberships')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The membership records linking this user to accounts.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get the user's personal account, if one exists.
     */
    public function personalAccount(): ?Account
    {
        return $this->accounts()
            ->where('type', AccountType::Personal)
            ->first();
    }
}
