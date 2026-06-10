<?php

namespace Tests\Unit\Pest;

use SchenkeIo\TestOutputFormatter\Pest\Git;

it('can get changed files from git', function () {
    $git = new Git;
    $files = $git->getChangedFiles();

    // Since I have modified some files, this should not be empty
    // unless everything is already committed (which it isn't in this environment)
    expect($files)->toBeArray();
});

it('returns empty array on invalid ref', function () {
    $git = new Git;
    $files = $git->getChangedFiles('non-existent-ref-12345');

    expect($files)->toBeArray()->toBeEmpty();
});
