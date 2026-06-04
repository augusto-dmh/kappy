<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\GitHubApp\Scm\GitHubScmDriver;

beforeEach(function () {
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($resource, $privateKey);

    config()->set('services.github-app.app_id', '12345');
    config()->set('services.github-app.private_key', $privateKey);

    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'ghs_installtoken'], 201),
    ]);
});

test('the container resolves the scm driver contract to the github implementation', function () {
    expect(app(ScmDriver::class))->toBeInstanceOf(GitHubScmDriver::class);
});

test('it fetches pull request metadata with the installation token', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/pulls/5' => Http::response([
            'number' => 5,
            'title' => 'Add widgets',
            'state' => 'open',
        ]),
    ]);

    $pullRequest = app(ScmDriver::class)->pullRequest(42, 'acme/widgets', 5);

    expect($pullRequest)->toBe([
        'number' => 5,
        'title' => 'Add widgets',
        'state' => 'open',
    ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets/pulls/5'
            && $request->hasHeader('Authorization', 'Bearer ghs_installtoken')
            && $request->hasHeader('Accept', 'application/vnd.github+json');
    });
});

test('it fetches the raw diff without persisting or logging it', function () {
    $diff = "diff --git a/widget.php b/widget.php\n+echo 'hello';\n";

    Http::fake([
        'api.github.com/repos/acme/widgets/pulls/5' => Http::response($diff),
    ]);

    expect(app(ScmDriver::class)->diff(42, 'acme/widgets', 5))->toBe($diff);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets/pulls/5'
            && $request->hasHeader('Authorization', 'Bearer ghs_installtoken')
            && $request->hasHeader('Accept', 'application/vnd.github.diff');
    });
});

test('it fetches review comments with the installation token', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/pulls/5/comments' => Http::response([
            ['id' => 1, 'body' => 'Looks good'],
            ['id' => 2, 'body' => 'Nit: rename this'],
        ]),
    ]);

    $comments = app(ScmDriver::class)->comments(42, 'acme/widgets', 5);

    expect($comments)->toHaveCount(2)
        ->and($comments[0]['body'])->toBe('Looks good');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets/pulls/5/comments'
            && $request->hasHeader('Authorization', 'Bearer ghs_installtoken');
    });
});

test('the write methods are not implemented in the read-only driver', function () {
    $driver = app(ScmDriver::class);

    expect(fn () => $driver->postComment(42, 'acme/widgets', 5, 'A finding'))
        ->toThrow(LogicException::class, 'posting lands in the review pipeline (Phase 3)')
        ->and(fn () => $driver->checkRun(42, 'acme/widgets', str_repeat('a', 40), 'kappy-review', 'No findings'))
        ->toThrow(LogicException::class, 'posting lands in the review pipeline (Phase 3)');
});
