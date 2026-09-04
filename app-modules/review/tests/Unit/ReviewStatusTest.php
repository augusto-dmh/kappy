<?php

use Modules\Review\Enums\ReviewStatus;

test('inboxGroup maps every status onto an inbox filter group', function (ReviewStatus $status) {
    $groups = [
        'completed' => 'completed',
        'failed' => 'failed',
        'skipped' => 'skipped',
        'queued' => 'in_progress',
        'fetching' => 'in_progress',
        'generating' => 'in_progress',
        'critiquing' => 'in_progress',
        'ready_to_post' => 'in_progress',
        'posting' => 'in_progress',
    ];

    expect($groups)->toHaveKey($status->value)
        ->and($status->inboxGroup())->toBe($groups[$status->value]);
})->with(ReviewStatus::cases());
