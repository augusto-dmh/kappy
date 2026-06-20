<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('install.callback'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the install callback page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('install.callback'))
        ->assertOk();
});

test('the install callback renders the expected Inertia component', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('install.callback'))
        ->assertInertia(fn (Assert $page) => $page->component('install/callback'));
});
