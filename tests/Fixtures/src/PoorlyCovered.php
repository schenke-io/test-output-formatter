<?php

namespace SchenkeIo\TestOutputFormatter\Tests\Fixtures\Src;

class PoorlyCovered
{
    public function uncoveredMethodOne(): string
    {
        return 'This line is not covered by tests.';
    }

    public function uncoveredMethodTwo(): string
    {
        return 'Neither is this one.';
    }
}
