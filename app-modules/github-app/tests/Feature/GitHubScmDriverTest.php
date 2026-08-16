<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
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

test('a comment without a path or line is posted as an issue comment', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/issues/5/comments' => Http::response(['id' => 556677], 201),
    ]);

    $id = app(ScmDriver::class)->postComment(42, 'acme/widgets', 5, 'A review summary');

    expect($id)->toBe(556677);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets/issues/5/comments'
            && $request->method() === 'POST'
            && $request->data() === ['body' => 'A review summary']
            && $request->hasHeader('Authorization', 'Bearer ghs_installtoken')
            && $request->hasHeader('Accept', 'application/vnd.github+json');
    });
});

test('a comment with a path and line is posted as an inline review comment on the new side', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/pulls/5/comments' => Http::response(['id' => 889900], 201),
    ]);

    $id = app(ScmDriver::class)->postComment(
        42,
        'acme/widgets',
        5,
        'A finding',
        'app/Widget.php',
        42,
        str_repeat('c', 40),
    );

    expect($id)->toBe(889900);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets/pulls/5/comments'
            && $request->method() === 'POST'
            && $request->data() === [
                'body' => 'A finding',
                'commit_id' => str_repeat('c', 40),
                'path' => 'app/Widget.php',
                'line' => 42,
                'side' => 'RIGHT',
            ]
            && $request->hasHeader('Authorization', 'Bearer ghs_installtoken');
    });
});

test('an inline comment without a commit sha is rejected before any request', function () {
    expect(fn () => app(ScmDriver::class)->postComment(42, 'acme/widgets', 5, 'A finding', 'app/Widget.php', 42))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/comments'));
});

test('a rejected inline comment surfaces as a request exception', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/pulls/5/comments' => Http::response(['message' => 'Validation Failed'], 422),
    ]);

    expect(fn () => app(ScmDriver::class)->postComment(42, 'acme/widgets', 5, 'A finding', 'app/Widget.php', 42, str_repeat('c', 40)))
        ->toThrow(RequestException::class);
});

test('a check run is created as a completed neutral run against the head sha', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/check-runs' => Http::response(['id' => 121314], 201),
    ]);

    $headSha = str_repeat('d', 40);

    $id = app(ScmDriver::class)->checkRun(42, 'acme/widgets', $headSha, 'kappy-review', 'One finding worth a look.');

    expect($id)->toBe(121314);

    Http::assertSent(function (Request $request) use ($headSha) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets/check-runs'
            && $request->method() === 'POST'
            && $request['name'] === 'kappy-review'
            && $request['head_sha'] === $headSha
            && $request['status'] === 'completed'
            && $request['conclusion'] === 'neutral'
            && $request['output']['summary'] === 'One finding worth a look.'
            && $request->hasHeader('Authorization', 'Bearer ghs_installtoken');
    });
});
