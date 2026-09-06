<?php

use App\Models\User;

test('guests receive not found for the retired catalog path', function () {
    $this->get('/catalog')->assertNotFound();
});

test('authenticated members receive not found for the retired catalog path', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/catalog')
        ->assertNotFound();
});

test('the catalog module directory is gone', function () {
    expect(is_dir(base_path('app-modules/catalog')))->toBeFalse();
});

test('composer does not require the catalog path package', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])->not->toHaveKey('modules/catalog');
});

test('app-modules contains only the product modules', function () {
    $directories = collect(scandir(base_path('app-modules')) ?: [])
        ->reject(fn (string $name): bool => in_array($name, ['.', '..'], true))
        ->filter(fn (string $name): bool => is_dir(base_path('app-modules/'.$name)))
        ->sort()
        ->values()
        ->all();

    expect($directories)->toBe(['github-app', 'identity', 'review']);
});
