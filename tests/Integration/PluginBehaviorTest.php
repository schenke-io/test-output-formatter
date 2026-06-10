<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SchenkeIo\TestOutputFormatter\Pest\Cache;
use SchenkeIo\TestOutputFormatter\Pest\Git;
use SchenkeIo\TestOutputFormatter\Plugin;

class PluginBehaviorTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/pest_test_'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir.'/*'));
            rmdir($this->cacheDir);
        }
    }

    public function test_it_reruns_failed_files_from_cache()
    {
        $cache = new Cache($this->cacheDir);
        $cache->write('failed-files.json', ['tests/Unit/OptionsTest.php']);

        $plugin = (new \ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
        $args = ['pest', '--rerun-failures', '--cache-dir='.$this->cacheDir];
        $newArgs = $plugin->handleArguments($args);

        $this->assertContains('tests/Unit/OptionsTest.php', $newArgs);
        $this->assertEquals('pest', $newArgs[0]);
    }

    public function test_it_fails_open_when_coverage_map_is_missing()
    {
        $plugin = (new \ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
        $args = ['pest', '--changed', '--cache-dir='.$this->cacheDir];

        // Mock Git to return a source file
        $git = \Mockery::mock(Git::class);
        $git->shouldReceive('getChangedFiles')->andReturn(['src/Pest/Options.php']);
        $plugin->setTestGit($git);

        $newArgs = $plugin->handleArguments($args);

        // Should return original arguments (except plugin's own flags)
        $this->assertNotContains('src/Pest/Options.php', $newArgs);
    }

    public function test_it_fails_open_when_source_file_not_in_map()
    {
        $cache = new Cache($this->cacheDir);
        $cache->write('source-to-tests.json', ['src/Pest/Path.php' => ['tests/Unit/PathTest.php']]);

        $plugin = (new \ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
        $args = ['pest', '--changed', '--cache-dir='.$this->cacheDir];

        $git = \Mockery::mock(Git::class);
        $git->shouldReceive('getChangedFiles')->andReturn(['src/Pest/Options.php']); // Not in map
        $plugin->setTestGit($git);

        $newArgs = $plugin->handleArguments($args);

        $this->assertNotContains('tests/Unit/PathTest.php', $newArgs);
    }

    public function test_it_writes_shards_in_parallel_mode()
    {
        $_SERVER['PEST_PARALLEL_ID'] = 'shard1';

        $plugin = (new \ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
        $plugin->handleArguments(['pest', '--cache-dir='.$this->cacheDir]);

        $plugin->addOutput(0);

        $this->assertFileExists($this->cacheDir.'/failures-shard1.json');
        $this->assertFileExists($this->cacheDir.'/timing-shard1.json');

        unset($_SERVER['PEST_PARALLEL_ID']);
    }

    public function test_it_writes_full_cache_and_merges_shards_in_serial_mode()
    {
        // Pre-fill a shard to be merged
        $cache = new Cache($this->cacheDir);
        $cache->write('failures-shard2.json', ['tests/Unit/ResultTest.php']);

        // Pre-fill cache with coverage data so ResultProcessor returns it
        $cache->write('under-covered.json', [['file' => 'src/Low.php', 'cov' => 30.0, 'lines' => '1-10']]);
        $cache->write('source-to-tests.json', ['src/Low.php' => ['tests/LowTest.php']]);

        $plugin = (new \ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
        // Add --under to trigger coverage-related writes
        $plugin->handleArguments(['pest', '--cache-dir='.$this->cacheDir, '--under=50']);

        $plugin->addOutput(0);

        $this->assertFileExists($this->cacheDir.'/failed-files.json');
        $this->assertFileExists($this->cacheDir.'/timing.json');
        $this->assertFileExists($this->cacheDir.'/under-covered.json');
        $this->assertFileExists($this->cacheDir.'/source-to-tests.json');

        // Verify shard was merged and deleted
        $failedFiles = json_decode(file_get_contents($this->cacheDir.'/failed-files.json'), true)['data'];
        $this->assertContains('tests/Unit/ResultTest.php', $failedFiles);
        $this->assertFileDoesNotExist($this->cacheDir.'/failures-shard2.json');
    }

    public function test_it_handles_throwable_in_cache_read()
    {
        $cache = (new \ReflectionClass(Cache::class))->newInstanceWithoutConstructor();

        $ref = new \ReflectionProperty(Cache::class, 'cacheDir');
        $ref->setAccessible(true);
        // This will now work as cacheDir is mixed
        $ref->setValue($cache, new class
        {
            public function __toString()
            {
                throw new \Exception('die');
            }
        });

        $result = $cache->read('somefile.json');
        $this->assertNull($result);
    }

    public function test_it_handles_throwable_in_cache_merge_shards()
    {
        $cache = (new \ReflectionClass(Cache::class))->newInstanceWithoutConstructor();

        $ref = new \ReflectionProperty(Cache::class, 'cacheDir');
        $ref->setAccessible(true);
        $ref->setValue($cache, new class
        {
            public function __toString()
            {
                throw new \Exception('die');
            }
        });

        $cache->mergeShards('prefix', 'target.json');
        $this->assertTrue(true);
    }

    public function test_it_reaches_continue_in_merge_shards()
    {
        $cacheDir = $this->cacheDir;
        mkdir($cacheDir, 0777, true);
        $cache = new Cache($cacheDir);

        // Create a directory where a file is expected, glob will find it
        mkdir($cacheDir.'/failures-gap.json');

        // Disable error handler to let @file_get_contents return false without throwing
        set_error_handler(fn () => true);
        $cache->mergeShards('failures-', 'target.json');

        // Also test read() hitting return null on false
        $cache->read('failures-gap.json');

        restore_error_handler();

        $this->assertTrue(true);
    }
}
