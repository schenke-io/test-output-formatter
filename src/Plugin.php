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
use SchenkeIo\TestOutputFormatter\Pest\JsonRenderer;
use SchenkeIo\TestOutputFormatter\Pest\Options;
use SchenkeIo\TestOutputFormatter\Pest\Path;
use SchenkeIo\TestOutputFormatter\Pest\ResultProcessor;
use SchenkeIo\TestOutputFormatter\Pest\TextRenderer;

class Plugin implements AddsOutput, HandlesArguments
{
    private Options $options;

    private static ?object $testEventFacade = null;

    /** @var string[] */
    private array $failedFiles = [];

    /** @var array<string, HRTime> */
    private array $testStartTimes = [];

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
        $this->testStartTimes[$event->test()->id()] = $event->telemetryInfo()->time();
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

        return $newArguments;
    }

    /**
     * Intercept and add custom output at the end of the test run.
     */
    public function addOutput(int $exitCode): int
    {
        $processor = new ResultProcessor($this->options);
        $result = $processor->process($this->failedFiles, $this->timing);

        if ($this->options->json) {
            return (new JsonRenderer)->render($result, $exitCode);
        }

        return (new TextRenderer($this->options))->render($result, $exitCode);
    }
}
