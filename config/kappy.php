<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Review
    |--------------------------------------------------------------------------
    |
    | Knobs for the code-review pipeline. The customer diff is reviewed in
    | memory and is never persisted or logged; nothing here stores source.
    |
    */

    'review' => [

        /*
         | The model the generate pass runs on. Read through config so a model
         | change is a deploy-time env change, never a code change.
         */
        'generator_model' => env('KAPPY_GENERATOR_MODEL', 'claude-opus-4-8'),

        /*
         | The cheaper tier the critic/verify pass will run on. Wired through
         | config now for a one-line switch later; it is not consumed yet.
         */
        'critic_model' => env('KAPPY_CRITIC_MODEL', 'claude-haiku-4-5-20251001'),

        /*
         | Diffs larger than this many lines are skipped rather than reviewed,
         | bounding cost and latency for very large pull requests.
         */
        'max_pr_diff_lines' => (int) env('KAPPY_MAX_PR_DIFF_LINES', 20000),

        /*
         | A hidden marker prepended to every comment Kappy posts so its own
         | content is identifiable on the pull request.
         */
        'ai_marker' => '<!-- kappy-review -->',

        /*
         | The lowest severity that may be posted as an inline comment, stored
         | as a FindingSeverity backing value. Findings below this threshold
         | are folded into the summary instead. Nit always routes to the
         | summary regardless of this setting.
         */
        'inline_min_severity' => 'low',

    ],

];
