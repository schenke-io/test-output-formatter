<?php

namespace SchenkeIo\TestOutputFormatter;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use SchenkeIo\TestOutputFormatter\Pest\Cache;
use SchenkeIo\TestOutputFormatter\Pest\Git;
use SchenkeIo\TestOutputFormatter\Pest\JsonRenderer;
use SchenkeIo\TestOutputFormatter\Pest\Options;
use SchenkeIo\TestOutputFormatter\Pest\Path;
use SchenkeIo\TestOutputFormatter\Pest\ResultProcessor;
use SchenkeIo\TestOutputFormatter\Pest\TextRenderer;

class Plugin implements AddsOutput, HandlesArguments
{
    private Options $options;

    private static ?object $testEventFacade = null;

    private static ?Git $testGit = null;

    /** @var string[] */
    private array $failedFiles = [];

    /** @var array<string, HRTime> */
    private array $testStartTimes = [];

    /** @var array<string, string> */
    private array $testIdToFile = [];

    /** @var array<int, array{file: string, test: string, ms: float}> */
    private array $timing = [];

    public function __construct()
    {
        $this->options = new Options;
        $this->registerSubscribers(self::$testEventFacade ?? EventFacade::instance());
    }

    /**
     * Allows injecting a mock EventFacade during unit testing.
     *
     * @internal
     */
    public static function setTestEventFacade(?object $eventFacade): void
    {
        self::$testEventFacade = $eventFacade;
    }

    /**
     * @internal
     */
    public static function setTestGit(?Git $git): void
    {
        self::$testGit = $git;
    }

    /**
     * Decoupled subscriber registration to allow testing with mock objects.
     *
     * @param  EventFacade|object  $eventFacade
     */
    public function registerSubscribers($eventFacade): void
    {
        /** @var EventFacade $eventFacade */
        $eventFacade->registerSubscriber(new class($this) implements FailedSubscriber
        {
            public function __construct(private Plugin $plugin) {}

            public function notify(Failed $event): void
            {
                $this->plugin->addFailedFile($event->test());
            }
        });

        $eventFacade->registerSubscriber(new class($this) implements PreparedSubscriber
        {
            public function __construct(private Plugin $plugin) {}

            public function notify(Prepared $event): void
            {
                $this->plugin->recordTestStart($event);
            }
        });

        $eventFacade->registerSubscriber(new class($this) implements FinishedSubscriber
        {
            public function __construct(private Plugin $plugin) {}

            public function notify(Finished $event): void
            {
                $this->plugin->recordTestEnd($event);
            }
        });
    }

    public function recordTestStart(Prepared $event): void
    {
        $test = $event->test();
        $this->testIdToFile[$test->id()] = Path::normalize($test->file());
        $this->testStartTimes[$test->id()] = $event->telemetryInfo()->time();
    }

    public function recordTestEnd(Finished $event): void
    {
        $test = $event->test();
        $id = $test->id();
        if (isset($this->testStartTimes[$id])) {
            $duration = $event->telemetryInfo()->time()->duration($this->testStartTimes[$id]);
            unset($this->testStartTimes[$id]);

            if ($test instanceof TestMethod) {
                $this->timing[] = [
                    'file' => Path::normalize($test->file()),
                    'test' => $test->name(),
                    'ms' => $duration->asFloat() * 1000,
                ];
            }
        }
    }

    public function addFailedFile(Test $test): void
    {
        if ($test instanceof TestMethod) {
            $this->failedFiles[] = Path::normalize($test->file());
        }
    }

    /**
     * Inspect and handle CLI arguments passed to Pest.
     */
    public function handleArguments(array $arguments): array
    {
        [$this->options, $newArguments] = Options::fromArguments($arguments);

        if ($this->options->rerunFailures && $this->options->cacheDir) {
            $cache = new Cache($this->options->cacheDir);
            $failedFiles = $cache->read('failed-files.json');
            if (is_array($failedFiles) && ! empty($failedFiles)) {
                $result = array_filter($failedFiles, fn ($f) => file_exists(getcwd().DIRECTORY_SEPARATOR.$f));
                if (! empty($result)) {
                    return array_merge([$arguments[0]], $result);
                }
            }
        }

        if ($this->options->since !== null || $this->options->changed) {
            $git = self::$testGit ?? new Git;
            $changedFiles = $git->getChangedFiles($this->options->since);
            if (! empty($changedFiles)) {
                $testFiles = [];
                $sourceFilesChanged = [];
                foreach ($changedFiles as $file) {
                    if (str_starts_with($file, 'tests/')) {
                        if (file_exists(getcwd().DIRECTORY_SEPARATOR.$file)) {
                            $testFiles[] = $file;
                        }
                    } elseif (str_starts_with($file, 'src/') || str_ends_with($file, '.php')) {
                        $sourceFilesChanged[] = $file;
                    }
                }

                if (! empty($sourceFilesChanged)) {
                    if ($this->options->cacheDir) {
                        $cache = new Cache($this->options->cacheDir);
                        $sourceToTests = $cache->read('source-to-tests.json');
                        if (is_array($sourceToTests)) {
                            foreach ($sourceFilesChanged as $file) {
                                if (isset($sourceToTests[$file])) {
                                    foreach ($sourceToTests[$file] as $testFile) {
                                        if (file_exists(getcwd().DIRECTORY_SEPARATOR.$testFile)) {
                                            $testFiles[] = $testFile;
                                        }
                                    }
                                } else {
                                    /*
                                     * we don't know which tests cover this source file,
                                     * so we must run all tests (fail open)
                                     */
                                    return $newArguments;
                                }
                            }
                        } else {
                            // no coverage map available, fail open
                            return $newArguments;
                        }
                    } else {
                        // no cache dir, fail open
                        return $newArguments;
                    }
                }

                if (! empty($testFiles)) {
                    return array_merge([$arguments[0]], array_unique($testFiles));
                }
            }
        }

        return $newArguments;
    }

    /**
     * Intercept and add custom output at the end of the test run.
     */
    public function addOutput(int $exitCode): int
    {
        $cache = $this->options->cacheDir ? new Cache($this->options->cacheDir) : null;
        $processor = new ResultProcessor($this->options, cache: $cache);
        $result = $processor->process($this->failedFiles, $this->timing, $this->testIdToFile);

        if ($cache) {
            $parallelId = $_SERVER['PEST_PARALLEL_ID'] ?? null;
            if ($parallelId) {
                $cache->write("failures-$parallelId.json", $result->failedFiles);
                $cache->write("timing-$parallelId.json", $this->timing);
            } else {
                $cache->write('failed-files.json', $result->failedFiles);
                $cache->write('timing.json', $this->timing);
                if (! empty($result->coverageMap)) {
                    $cache->write('source-to-tests.json', $result->coverageMap);
                }
                if (! empty($result->underCovered)) {
                    $cache->write('under-covered.json', $result->underCovered);
                }
                /*
                 * if we just finished a parallel run, the shards have
                 * written their results to the cache, and we merge them now
                 */
                $cache->mergeShards('failures-', 'failed-files.json', true);
                $cache->mergeShards('timing-', 'timing.json');
            }
        }

        if ($this->options->json) {
            return (new JsonRenderer)->render($result, $exitCode);
        }

        return (new TextRenderer($this->options))->render($result, $exitCode);
    }
}
