<?php

namespace Tests\Unit;

use Mockery;
use SchenkeIo\TestOutputFormatter\Pest\Git;
use SchenkeIo\TestOutputFormatter\Plugin;

beforeEach(function () {
    $eventFacade = Mockery::mock();
    $eventFacade->shouldReceive('registerSubscriber');
    Plugin::setTestEventFacade($eventFacade);
});

afterEach(function () {
    Plugin::setTestGit(null);
    Plugin::setTestEventFacade(null);
    Mockery::close();
});

it('narrows to changed test files when no source files changed', function () {
    $git = Mockery::mock(Git::class);
    $git->shouldReceive('getChangedFiles')
        ->with('HEAD')
        ->andReturn([
            'tests/Unit/OptionsTest.php',
            'tests/Integration/Pest/CacheTest.php',
        ]);

    Plugin::setTestGit($git);

    // Create these files in the real filesystem if needed, but since we are in a real repo,
    // let's hope these paths exist or we use existing ones.
    // Better to use existing ones or mock file_exists.
    // Wait, the Plugin uses file_exists(getcwd().DIRECTORY_SEPARATOR.$file).

    $existingTest = 'tests/Unit/OptionsTest.php';

    $git->shouldReceive('getChangedFiles')
        ->with(null)
        ->andReturn([$existingTest]);

    $plugin = new Plugin;
    $args = ['pest', '--changed'];

    $result = $plugin->handleArguments($args);

    expect($result)->toBe(['pest', $existingTest]);
});

it('fails open when source files changed', function () {
    $git = Mockery::mock(Git::class);
    $git->shouldReceive('getChangedFiles')
        ->andReturn([
            'src/Pest/Options.php',
            'tests/Unit/OptionsTest.php',
        ]);

    Plugin::setTestGit($git);

    $plugin = new Plugin;
    $args = ['pest', '--changed'];

    $result = $plugin->handleArguments($args);

    // Should return original arguments (without --changed)
    expect($result)->toBe(['pest']);
});

it('ignores non-PHP source changes but still fails open if they might be relevant', function () {
    // Current implementation: elseif (str_starts_with($file, 'src/') || str_ends_with($file, '.php'))
    // So a .js file in src/ would trigger sourceFilesChanged = true.
    // A .md file in root would not.

    $git = Mockery::mock(Git::class);
    $git->shouldReceive('getChangedFiles')
        ->andReturn([
            'README.md',
            'tests/Unit/OptionsTest.php',
        ]);

    Plugin::setTestGit($git);

    $plugin = new Plugin;
    $args = ['pest', '--changed'];

    $result = $plugin->handleArguments($args);

    expect($result)->toBe(['pest', 'tests/Unit/OptionsTest.php']);
});
