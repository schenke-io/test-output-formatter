<?php

namespace SchenkeIo\TestOutputFormatter\Tests\Fixtures\Tests;

use PHPUnit\Framework\TestCase;

class FailingTest extends TestCase
{
    public function test_this_test_is_written_to_fail()
    {
        $this->assertTrue(false);
    }
}
