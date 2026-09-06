<?php

use Inertia\Testing\AssertableInertia as Assert;

test('guests see the kappy welcome page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

test('the welcome page copy names kappy and drops laravel marketing', function () {
    $source = (string) file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($source)->toContain('Kappy')
        ->and($source)->toContain('Log in')
        ->and($source)->toContain('Register')
        ->and($source)->not->toContain("Let's get started")
        ->and($source)->not->toContain('laravel.com/docs')
        ->and($source)->not->toContain('laracasts.com')
        ->and($source)->not->toContain('cloud.laravel.com');
});
