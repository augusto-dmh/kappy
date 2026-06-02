<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the login page renders and exposes the github sign-in route', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login'));

    expect(route('auth.github.redirect', absolute: false))->toBe('/auth/github/redirect');
});
