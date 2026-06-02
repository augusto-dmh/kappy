<?php

namespace Modules\Identity\Policies;

use App\Models\User;
use Modules\Identity\Models\Account;

class AccountPolicy
{
    /**
     * Determine whether the user can view the account.
     */
    public function view(User $user, Account $account): bool
    {
        return $this->isMember($user, $account);
    }

    /**
     * Determine whether the user can update the account.
     */
    public function update(User $user, Account $account): bool
    {
        return $this->isMember($user, $account);
    }

    /**
     * Determine whether the user holds a membership on the account.
     */
    private function isMember(User $user, Account $account): bool
    {
        return $account->memberships()
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
