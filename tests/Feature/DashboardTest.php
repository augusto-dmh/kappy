<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard lists the accounts the user belongs to', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'name' => 'Ada Lovelace',
        'github_login' => 'ada',
    ]);
    Membership::factory()->for($user)->for($account)->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('accounts', 1)
            ->where('accounts.0.id', $account->id)
            ->where('accounts.0.name', 'Ada Lovelace')
            ->where('accounts.0.github_login', 'ada')
            ->where('accounts.0.type', 'personal')
            ->where('accounts.0.role', 'owner')
        );
});

test('the dashboard does not expose accounts belonging to other users', function () {
    $user = User::factory()->create();
    $ownAccount = Account::factory()->create(['github_login' => 'mine']);
    Membership::factory()->for($user)->for($ownAccount)->owner()->create();

    $otherUser = User::factory()->create();
    $otherAccount = Account::factory()->create(['github_login' => 'theirs']);
    Membership::factory()->for($otherUser)->for($otherAccount)->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('accounts', 1)
            ->where('accounts.0.id', $ownAccount->id)
            ->where('accounts.0.github_login', 'mine')
            ->where('accounts', fn ($accounts) => collect($accounts)->doesntContain('github_login', 'theirs'))
        );
});

test('the dashboard maps the organization type and a non-owner role', function () {
    $user = User::factory()->create();
    $account = Account::factory()->organization()->create();
    Membership::factory()->for($user)->for($account)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('accounts', 1)
            ->where('accounts.0.type', 'organization')
            ->where('accounts.0.role', 'member')
        );
});

test('the dashboard renders an empty account list for a user with no accounts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('accounts', 0)
        );
});
