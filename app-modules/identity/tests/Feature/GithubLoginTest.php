<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Modules\Identity\Actions\ProvisionUserFromGithub;
use Modules\Identity\Enums\AccountType;
use Modules\Identity\Enums\MembershipRole;
use Modules\Identity\Models\Account;
use Modules\Identity\Models\Membership;

/**
 * Build a fake GitHub identity without touching github.com.
 */
function fakeGithubUser(array $overrides = []): SocialiteUser
{
    return (new SocialiteUser)->map(array_merge([
        'id' => '583231',
        'nickname' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@example.com',
        'avatar' => 'https://avatars.githubusercontent.com/u/583231',
        'token' => 'gho_freshtoken',
    ], $overrides));
}

/**
 * Make the GitHub Socialite driver return the given fake user on callback.
 */
function mockGithubCallback(SocialiteUser $githubUser): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($githubUser);

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
}

test('the redirect endpoint sends the visitor to github with the read and email scopes', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('scopes')->with(['read:user', 'user:email'])->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn(redirect('https://github.com/login/oauth/authorize'));

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $this->get(route('auth.github.redirect'))
        ->assertRedirect('https://github.com/login/oauth/authorize');
});

test('a new github user is provisioned with a personal account and owner membership', function () {
    mockGithubCallback(fakeGithubUser([
        'id' => '12345',
        'nickname' => 'newdev',
        'name' => 'New Dev',
        'email' => 'newdev@example.com',
        'token' => 'gho_token123',
    ]));

    $response = $this->get(route('auth.github.callback', ['code' => 'valid-code']));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    // The freshly provisioned user clears the auth + verified gate on the dashboard.
    $this->get(route('dashboard'))->assertOk();

    $user = User::where('github_id', 12345)->sole();

    expect($user->email)->toBe('newdev@example.com')
        ->and($user->github_login)->toBe('newdev')
        ->and($user->avatar_url)->not->toBeNull()
        ->and($user->github_token)->toBe('gho_token123')
        ->and($user->email_verified_at)->not->toBeNull();

    $account = $user->personalAccount();

    expect($account)->not->toBeNull()
        ->and($account->type)->toBe(AccountType::Personal)
        ->and($account->github_account_id)->toEqual(12345);

    $membership = $user->memberships()->where('account_id', $account->id)->sole();

    expect($membership->role)->toBe(MembershipRole::Owner);
});

test('a returning github user is matched without duplicates and has the stored token refreshed', function () {
    $existing = User::factory()->create([
        'github_id' => 999,
        'github_login' => 'veteran',
        'github_token' => 'gho_old',
    ]);
    $account = Account::factory()->create([
        'github_account_id' => 999,
        'github_login' => 'veteran',
    ]);
    $account->members()->attach($existing, ['role' => MembershipRole::Owner]);

    mockGithubCallback(fakeGithubUser([
        'id' => '999',
        'nickname' => 'veteran',
        'email' => $existing->email,
        'token' => 'gho_new',
    ]));

    $response = $this->get(route('auth.github.callback', ['code' => 'valid-code']));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($existing);

    expect(User::count())->toBe(1)
        ->and(Account::count())->toBe(1)
        ->and(Membership::count())->toBe(1)
        ->and($existing->fresh()->github_token)->toBe('gho_new');
});

test('a password user with a matching email is linked instead of duplicated', function () {
    $existing = User::factory()->create([
        'email' => 'linkme@example.com',
        'github_id' => null,
        'github_login' => null,
    ]);

    mockGithubCallback(fakeGithubUser([
        'id' => '777',
        'nickname' => 'linkme',
        'email' => 'linkme@example.com',
        'token' => 'gho_linked',
    ]));

    $response = $this->get(route('auth.github.callback', ['code' => 'valid-code']));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($existing);

    expect(User::count())->toBe(1)
        ->and(Account::count())->toBe(0);

    $existing->refresh();

    expect($existing->github_id)->toEqual(777)
        ->and($existing->github_login)->toBe('linkme')
        ->and($existing->github_token)->toBe('gho_linked');
});

test('a github user without a public email is still provisioned with a null email', function () {
    mockGithubCallback(fakeGithubUser([
        'id' => '321',
        'nickname' => 'noemail',
        'name' => 'No Email',
        'email' => null,
        'token' => 'gho_noemail',
    ]));

    $response = $this->get(route('auth.github.callback', ['code' => 'valid-code']));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::where('github_id', 321)->sole();

    expect($user->email)->toBeNull()
        ->and($user->personalAccount())->not->toBeNull();
});

test('a cancelled github authorization redirects to login with an error and creates nothing', function () {
    $response = $this->get(route('auth.github.callback', ['error' => 'access_denied']));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('github');
    $this->assertGuest();

    expect(User::count())->toBe(0)
        ->and(Account::count())->toBe(0);
});

test('a callback with neither code nor error is treated as a cancellation', function () {
    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('github');
    $this->assertGuest();

    expect(User::count())->toBe(0)
        ->and(Account::count())->toBe(0);
});

test('a github user without a login is provisioned with a fallback account login', function () {
    mockGithubCallback(fakeGithubUser([
        'id' => '888',
        'nickname' => null,
        'name' => 'No Login',
        'email' => 'nologin@example.com',
        'token' => 'gho_nologin',
    ]));

    $response = $this->get(route('auth.github.callback', ['code' => 'valid-code']));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::where('github_id', 888)->sole();
    $account = $user->personalAccount();

    expect($account)->not->toBeNull()
        ->and($account->github_login)->toBe('github-888');
});

test('provisioning rolls back fully when account creation fails midway', function () {
    // An orphan account whose GitHub id collides with the incoming user forces the
    // Account insert to fail after the User has already been created in the transaction.
    Account::factory()->create(['github_account_id' => 555]);

    $action = app(ProvisionUserFromGithub::class);
    $githubUser = fakeGithubUser([
        'id' => '555',
        'nickname' => 'collider',
        'email' => 'collider@example.com',
    ]);

    expect(fn () => $action->execute($githubUser))->toThrow(QueryException::class);

    expect(User::where('github_id', 555)->exists())->toBeFalse()
        ->and(User::count())->toBe(0);
});
