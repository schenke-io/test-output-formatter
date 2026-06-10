<?php

namespace Tests\Integration;

use SchenkeIo\TestOutputFormatter\Pest\Cache;

it('writes and reads cache with valid stamp', function () {
    $cacheDir = sys_get_temp_dir().'/pest_cache_'.uniqid();
    $cache = new Cache($cacheDir);

    $data = ['file1.php', 'file2.php'];
    $cache->write('test.json', $data);

    expect($cache->read('test.json'))->toBe($data);

    // Clean up
    array_map('unlink', glob("$cacheDir/*"));
    rmdir($cacheDir);
});

it('returns null on invalid stamp', function () {
    $cacheDir = sys_get_temp_dir().'/pest_cache_'.uniqid();
    $cache = new Cache($cacheDir);

    $data = ['file1.php'];
    $cache->write('test.json', $data);

    // Manually corrupt the stamp in the file
    $path = $cacheDir.'/test.json';
    $payload = json_decode(file_get_contents($path), true);
    $payload['stamp'] = 'invalid';
    file_put_contents($path, json_encode($payload));

    expect($cache->read('test.json'))->toBeNull();

    // Clean up
    array_map('unlink', glob("$cacheDir/*"));
    rmdir($cacheDir);
});

it('merges shards correctly', function () {
    $cacheDir = sys_get_temp_dir().'/pest_cache_'.uniqid();
    $cache = new Cache($cacheDir);

    $cache->write('failures-1.json', ['file1.php']);
    $cache->write('failures-2.json', ['file2.php', 'file1.php']);

    $cache->mergeShards('failures-', 'failed-files.json', true);

    $merged = $cache->read('failed-files.json');
    expect($merged)->toHaveCount(2);
    expect($merged)->toContain('file1.php');
    expect($merged)->toContain('file2.php');

    expect(glob("$cacheDir/failures-*"))->toBeEmpty();

    // Clean up
    array_map('unlink', glob("$cacheDir/*"));
    rmdir($cacheDir);
});

it('returns null when file_get_contents fails in read', function () {
    $cacheDir = sys_get_temp_dir().'/pest_cache_'.uniqid();
    $cache = new Cache($cacheDir);

    // Create a directory where a file is expected
    mkdir($cacheDir.'/fail.json');

    expect($cache->read('fail.json'))->toBeNull();

    // Clean up
    rmdir($cacheDir.'/fail.json');
    rmdir($cacheDir);
});

it('returns null when json is invalid in read', function () {
    $cacheDir = sys_get_temp_dir().'/pest_cache_'.uniqid();
    $cache = new Cache($cacheDir);

    file_put_contents($cacheDir.'/invalid.json', 'invalid json');

    expect($cache->read('invalid.json'))->toBeNull();

    // Clean up
    unlink($cacheDir.'/invalid.json');
    rmdir($cacheDir);
});

it('continues when file_get_contents fails in mergeShards', function () {
    $cacheDir = sys_get_temp_dir().'/pest_cache_'.uniqid();
    $cache = new Cache($cacheDir);

    // Create a directory where a file is expected by glob
    mkdir($cacheDir.'/failures-1.json');

    // This should not throw and just continue
    $cache->mergeShards('failures-', 'target.json');

    expect($cache->read('target.json'))->toBeNull();

    // Clean up
    rmdir($cacheDir.'/failures-1.json');
    rmdir($cacheDir);
});
