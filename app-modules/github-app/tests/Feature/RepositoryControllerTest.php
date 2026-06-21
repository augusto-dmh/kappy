<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\Repository;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;

test('guests are redirected to the login page on the repositories index', function () {
    $this->get(route('repositories.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the repositories index', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('repositories.index'))
        ->assertOk();
});

test('the repositories index renders the expected Inertia component', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('repositories.index'))
        ->assertInertia(fn (Assert $page) => $page->component('repositories/index'));
});

test('the repositories index lists only repositories belonging to the authenticated user', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    Membership::factory()->for($user)->for($account)->owner()->create();
    $installation = Installation::factory()->for($account)->create();
    $repo = Repository::factory()->for($installation)->create(['full_name' => 'mine/repo']);

    $otherUser = User::factory()->create();
    $otherAccount = Account::factory()->create();
    Membership::factory()->for($otherUser)->for($otherAccount)->owner()->create();
    $otherInstallation = Installation::factory()->for($otherAccount)->create();
    Repository::factory()->for($otherInstallation)->create(['full_name' => 'theirs/repo']);

    $this->actingAs($user)
        ->get(route('repositories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('repositories/index')
            ->has('repositories', 1)
            ->where('repositories.0.id', $repo->id)
            ->where('repositories.0.full_name', 'mine/repo')
        );
});

test('toggling review_enabled on an owned repository persists the change', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    Membership::factory()->for($user)->for($account)->owner()->create();
    $installation = Installation::factory()->for($account)->create();
    $repo = Repository::factory()->for($installation)->reviewDisabled()->create();

    $this->actingAs($user)
        ->patch(route('repositories.update', $repo), ['review_enabled' => true])
        ->assertRedirect();

    expect($repo->fresh()->review_enabled)->toBeTrue();
});

test('toggling review_enabled on another user\'s repository is denied', function () {
    $user = User::factory()->create();

    $otherAccount = Account::factory()->create();
    $otherInstallation = Installation::factory()->for($otherAccount)->create();
    $otherRepo = Repository::factory()->for($otherInstallation)->create();

    $this->actingAs($user)
        ->patch(route('repositories.update', $otherRepo), ['review_enabled' => false])
        ->assertForbidden();
});
