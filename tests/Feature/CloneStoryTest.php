<?php

test('the repository has a public product readme', function () {
    expect(is_file(base_path('README.md')))->toBeTrue();

    $readme = (string) file_get_contents(base_path('README.md'));

    expect($readme)->toContain('Kappy is an AI GitHub PR reviewer')
        ->and($readme)->not->toContain('kappy-research')
        ->and(mb_strtolower($readme))->not->toContain('interview');
});

test('composer names the package kappy', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['name'])->toBe('augusto-dmh/kappy')
        ->and($composer['description'])->not->toBe('The skeleton application for the Laravel framework.');
});

test('module conventions use wayfinder for the reviews breadcrumb', function () {
    $readme = (string) file_get_contents(base_path('app-modules/README.md'));

    expect($readme)->toContain('href: index()')
        ->and($readme)->not->toContain("href: '/reviews'");
});
