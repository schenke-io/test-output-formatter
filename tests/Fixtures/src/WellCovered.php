<?php

namespace SchenkeIo\TestOutputFormatter\Tests\Fixtures\Src;

class WellCovered
{
    public function coveredMethod(): string
    {
        return 'This is covered.';
    }

    public function coveredMethodTwo(): string
    {
        return 'This is also covered.';
    }
}
