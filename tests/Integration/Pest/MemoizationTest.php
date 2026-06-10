<?php

use SchenkeIo\TestOutputFormatter\Pest\Cache;
use SchenkeIo\TestOutputFormatter\Pest\Options;
use SchenkeIo\TestOutputFormatter\Pest\ResultProcessor;

beforeEach(function () {
    $this->cacheDir = getcwd().'/.test_cache_memo';
    if (! is_dir($this->cacheDir)) {
        mkdir($this->cacheDir, 0777, true);
    }
    $this->cache = new Cache($this->cacheDir);
});

afterEach(function () {
    exec('rm -rf '.$this->cacheDir);
});

it('memoises under-coverage results', function () {
    $options = new Options(under: 80, cacheDir: $this->cacheDir);

    // 1. Create a fake under-covered result in cache
    $fakeUnderCovered = [
        ['file' => 'src/FakeFile', 'cov' => 50.0, 'lines' => '1-10'],
    ];
    $fakeMap = ['src/FakeFile.php' => ['tests/FakeTest.php']];
    $this->cache->write('under-covered.json', $fakeUnderCovered);
    $this->cache->write('source-to-tests.json', $fakeMap);

    // 2. Run processor - it should pick up the cached result even without a coverage file
    $processor = new ResultProcessor($options, coveragePath: '/non/existent', cache: $this->cache);
    $result = $processor->process([], []);

    expect($result->underCovered)->toEqual($fakeUnderCovered);
    expect($result->coverageMap)->toEqual($fakeMap);
});

it('does not use stale cache for under-coverage', function () {
    $options = new Options(under: 80, cacheDir: $this->cacheDir);

    // 1. Write something to cache
    $this->cache->write('under-covered.json', [['file' => 'old']]);

    // 2. Tamper with the stamp (by changing a file that getStamp uses)
    // Actually, it's easier to just mock the Cache to return a different stamp or just use the real one.
    // If I change tests/Pest.php it should change the stamp.
    $pestFile = getcwd().'/tests/Pest.php';
    $originalContent = file_exists($pestFile) ? file_get_contents($pestFile) : null;

    touch($pestFile, time() - 100); // Set back in time
    $stamp1 = $this->cache->getStamp();

    touch($pestFile, time()); // Update mtime
    $stamp2 = $this->cache->getStamp();

    if ($stamp1 === $stamp2) {
        // Stamp didn't change, maybe filemtime resolution is too low?
        // Let's try appending something.
        file_put_contents($pestFile, ($originalContent ?? '')."// update\n");
    }

    // 3. Now the cache should be invalid
    $processor = new ResultProcessor($options, coveragePath: '/non/existent', cache: $this->cache);
    $result = $processor->process([], []);

    expect($result->underCovered)->toBeEmpty();

    // Cleanup
    if ($originalContent === null) {
        unlink($pestFile);
    } else {
        file_put_contents($pestFile, $originalContent);
    }
});
