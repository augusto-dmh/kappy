<?php

use Illuminate\Database\QueryException;
use Modules\GitHubApp\Models\Installation;
use Modules\GitHubApp\Models\Repository;

test('the factory creates a repository under an installation', function () {
    $repository = Repository::factory()->create();

    $this->assertModelExists($repository);
    expect($repository->installation)->toBeInstanceOf(Installation::class);
});

test('deleting the installation cascades to its repositories', function () {
    $repository = Repository::factory()->create();

    $repository->installation->delete();

    expect(Repository::find($repository->id))->toBeNull();
});

test('the github repo id must be unique', function () {
    $repository = Repository::factory()->create();

    expect(fn () => Repository::factory()->create([
        'github_repo_id' => $repository->github_repo_id,
    ]))->toThrow(QueryException::class);
});

test('the private and review_enabled attributes cast to booleans', function () {
    $repository = Repository::factory()->create([
        'private' => 1,
        'review_enabled' => 0,
    ])->fresh();

    expect($repository->private)->toBeBool()->toBeTrue()
        ->and($repository->review_enabled)->toBeBool()->toBeFalse();
});

test('an installation exposes its repositories', function () {
    $installation = Installation::factory()->create();
    Repository::factory()->for($installation)->create();

    expect($installation->repositories)->toHaveCount(1)
        ->and($installation->repositories->first())->toBeInstanceOf(Repository::class);
});
