<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\GitHubApp\Services\GitHubAppAuthenticator;

/**
 * Generate a throwaway RSA keypair so tests never touch a real App key.
 *
 * @return array{private: string, public: string}
 */
function generateRsaKeypair(): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $privateKey);

    return [
        'private' => $privateKey,
        'public' => openssl_pkey_get_details($resource)['key'],
    ];
}

beforeEach(function () {
    $this->keypair = generateRsaKeypair();

    config()->set('services.github-app.app_id', '12345');
    config()->set('services.github-app.private_key', $this->keypair['private']);

    Http::preventStrayRequests();
});

test('it exchanges a signed app jwt for the installation access token', function () {
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response([
            'token' => 'ghs_faketoken',
            'expires_at' => now()->addHour()->toIso8601String(),
        ], 201),
    ]);

    $token = app(GitHubAppAuthenticator::class)->installationToken(42);

    expect($token)->toBe('ghs_faketoken');

    Http::assertSent(function (Request $request) {
        if ($request->url() !== 'https://api.github.com/app/installations/42/access_tokens') {
            return false;
        }

        if (! $request->hasHeader('Accept', 'application/vnd.github+json')) {
            return false;
        }

        $authorization = $request->header('Authorization')[0] ?? '';

        if (! str_starts_with($authorization, 'Bearer ')) {
            return false;
        }

        $jwt = substr($authorization, strlen('Bearer '));

        if (count(explode('.', $jwt)) !== 3) {
            return false;
        }

        // Decoding with the keypair's public half also proves the RS256 signature.
        $claims = JWT::decode($jwt, new Key($this->keypair['public'], 'RS256'));

        return $claims->iss === '12345'
            && $claims->iat <= time() - 60
            && $claims->iat >= time() - 120
            && $claims->exp > time()
            && $claims->exp <= time() + 600;
    });
});

test('it reads the private key from a file when the config value is a path', function () {
    $path = tempnam(sys_get_temp_dir(), 'kappy-test-key-');
    file_put_contents($path, $this->keypair['private']);
    config()->set('services.github-app.private_key', $path);

    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'ghs_faketoken'], 201),
    ]);

    try {
        $token = app(GitHubAppAuthenticator::class)->installationToken(7);
    } finally {
        unlink($path);
    }

    expect($token)->toBe('ghs_faketoken');

    Http::assertSent(function (Request $request) {
        $jwt = substr($request->header('Authorization')[0] ?? '', strlen('Bearer '));

        return JWT::decode($jwt, new Key($this->keypair['public'], 'RS256'))->iss === '12345';
    });
});

test('a failed token exchange surfaces without leaking the jwt or private key', function () {
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['message' => 'Integration not found'], 404),
    ]);

    try {
        app(GitHubAppAuthenticator::class)->installationToken(42);

        $this->fail('A RequestException should have been thrown.');
    } catch (RequestException $exception) {
        expect($exception->getMessage())
            ->not->toContain('PRIVATE KEY')
            ->not->toContain('Bearer');
    }
});
